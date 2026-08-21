<?php
/**
 * A read-only diagnostic snapshot of a plugin's state.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Diagnostics;

use WPHEKA\Framework\V1\Core\Framework;
use WPHEKA\Framework\V1\Core\Logger;
use WPHEKA\Framework\V1\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Builds one snapshot a developer can read, download, or receive from a customer.
 *
 * The point is to answer "why is this site behaving oddly?" without a support
 * round-trip. Production runs with `WP_DEBUG` off, so a failure that does not
 * fatal leaves no trace anywhere — this is how it becomes visible.
 *
 * Generalised from wpheka-seo-os src/Tools/Diagnostics.php, keeping the parts
 * that are not plugin-specific and, more importantly, the lessons that version
 * learned the hard way. It is strictly read-only: it never writes to the
 * database and never changes behaviour.
 */
final class Report {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Extra sections added by the plugin.
	 *
	 * @var array<string, mixed>
	 */
	private array $sections = array();

	/**
	 * Construct a report for one plugin.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $version Plugin version.
	 */
	public function __construct( string $slug, string $version ) {
		$this->slug    = $slug;
		$this->version = $version;
	}

	/**
	 * Add a plugin-specific section.
	 *
	 * Values are redacted on output, so passing settings wholesale is safe.
	 *
	 * @param string $name Section name.
	 * @param mixed  $data Section contents.
	 * @return self
	 */
	public function section( string $name, $data ): self {
		$this->sections[ $name ] = $data;

		return $this;
	}

	/**
	 * Record which tables a schema expects and which are actually missing.
	 *
	 * Missing tables are reported as a fault rather than a statistic. In
	 * wpheka-seo-os a table was lost to a failed migration and, because the
	 * stored schema version still matched, nothing ever re-ran dbDelta: the
	 * feature produced nothing for weeks while still spending API quota. Only
	 * asking the database catches that.
	 *
	 * @param Schema $schema Schema to inspect.
	 * @return self
	 */
	public function schema( Schema $schema ): self {
		$missing = $schema->missing();

		return $this->section(
			'schema',
			array(
				'expected' => $schema->names(),
				'missing'  => $missing,
				'ok'       => array() === $missing,
			)
		);
	}

	/**
	 * Record the notices this plugin raised during the request.
	 *
	 * @param NoticeLog $notices Collector.
	 * @return self
	 */
	public function notices( NoticeLog $notices ): self {
		$all = $notices->all();

		return $this->section(
			'notices',
			array(
				'count'    => count( $all ),
				// Notices raised before `init` are the early-i18n class (ADR-007),
				// which is silent in production and worth surfacing separately.
				'pre_init' => count( array_filter( $all, static fn( $n ) => ! empty( $n['pre_init'] ) ) ),
				'entries'  => $all,
			)
		);
	}

	/**
	 * Assert that a hook carries a callback owned by a given object.
	 *
	 * `has_action( $hook )` is useless on shared hooks like `wp_head` or
	 * `save_post`: something is always attached. Proving *our* callback is
	 * attached means walking the filter list and matching the owning object.
	 *
	 * Three things caused false alarms when wpheka-seo-os first did this, and
	 * the return values here reflect all three:
	 *
	 * - **Context.** Front-end subsystems return early on admin requests, so
	 *   their hooks are legitimately absent where a report usually runs. The
	 *   caller decides which context an assertion is meaningful in.
	 * - **Callback form.** Closures and plain function names cannot be
	 *   attributed to a class, so those degrade to `attached` rather than
	 *   being reported as a failure.
	 * - **Deliberate toggles.** A disabled feature has no hook by design, so
	 *   absence is reported as `absent`, not as broken.
	 *
	 * @param string $hook  Hook name.
	 * @param object $owner Object whose method should be attached.
	 * @return string One of: owned, attached, absent.
	 */
	public static function hook_owned_by( string $hook, object $owner ): string {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return 'absent';
		}

		$callbacks = $wp_filter[ $hook ]->callbacks ?? array();

		foreach ( $callbacks as $by_priority ) {
			foreach ( $by_priority as $registered ) {
				$callback = $registered['function'] ?? null;

				if ( is_array( $callback ) && isset( $callback[0] ) && $callback[0] === $owner ) {
					return 'owned';
				}
			}
		}

		// Something is attached, but nothing we can attribute to this object —
		// which is the expected answer for closures and function names.
		return 'attached';
	}

	/**
	 * The environment the plugin is running in.
	 *
	 * @return array<string, mixed>
	 */
	public function environment(): array {
		global $wp_version, $wpdb;

		return array(
			'php'          => PHP_VERSION,
			'wordpress'    => $wp_version,
			'woocommerce'  => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'multisite'    => is_multisite(),
			'blog_id'      => get_current_blog_id(),
			'network_site' => is_multisite() ? get_current_network_id() : null,
			'wp_debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'db_prefix'    => $wpdb->prefix,
			'base_prefix'  => $wpdb->base_prefix,
			'locale'       => get_locale(),
		);
	}

	/**
	 * Which framework build booted, and which modules it carries.
	 *
	 * Bundling is modular (ADR-016), so "which framework version" is only half
	 * the question — a build can be new enough and still lack a module the
	 * plugin needs.
	 *
	 * @return array<string, mixed>
	 */
	public function framework(): array {
		$modules = array();

		foreach ( array(
			'Database'    => Schema::class,
			'Diagnostics' => self::class,
		) as $name => $probe ) {
			$modules[ $name ] = class_exists( $probe );
		}

		return array(
			'version'    => Framework::version(),
			'directory'  => Framework::dir(),
			'registered' => class_exists( 'WPHEKA_Framework_Versions', false )
				? array_keys( \WPHEKA_Framework_Versions::registered( '1' ) )
				: array(),
			'modules'    => $modules,
		);
	}

	/**
	 * The whole report, redacted and ready to render.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return Logger::redact(
			array_merge(
				array(
					'plugin'      => $this->slug,
					'version'     => $this->version,
					'generated'   => gmdate( 'c' ),
					'environment' => $this->environment(),
					'framework'   => $this->framework(),
				),
				$this->sections
			)
		);
	}

	/**
	 * Expose the report through WordPress' own Site Health screen.
	 *
	 * This matters more than a bespoke admin page: Site Health's "Copy site
	 * info" button means a customer can send a complete report without being
	 * walked through anything, and it keeps working when a plugin's own admin
	 * UI is the thing that is broken.
	 *
	 * The label is supplied by the caller rather than translated here. Bundled
	 * framework code has no text domain of its own -- no consumer plugin ever
	 * loads one -- so anything it translated would be permanently untranslated
	 * (ADR-019).
	 *
	 * @param string $label Panel heading, already translated by the plugin.
	 * @return void
	 */
	public function register_site_health( string $label = '' ): void {
		$label = '' === $label ? 'WPHEKA: ' . $this->slug : $label;

		add_filter(
			'debug_information',
			function ( $info ) use ( $label ) {
				$fields = array();

				foreach ( $this->to_array() as $key => $value ) {
					$fields[ $key ] = array(
						'label' => $key,
						'value' => is_scalar( $value ) || null === $value
							? (string) wp_json_encode( $value )
							: (string) wp_json_encode( $value, JSON_PRETTY_PRINT ),
					);
				}

				$info[ 'wpheka-' . $this->slug ] = array(
					'label'   => $label,

					/*
					 * Excluded from "Copy site info". The report is redacted by key
					 * name, which catches what it is asked to catch and nothing
					 * else: a plugin section is arbitrary caller data, and site
					 * URLs, table prefixes and licence seat detail have no business
					 * on a clipboard headed for a public support forum. Anyone
					 * debugging still reads it on the screen itself.
					 */
					'private' => true,
					'fields'  => $fields,
				);

				return $info;
			}
		);
	}
}
