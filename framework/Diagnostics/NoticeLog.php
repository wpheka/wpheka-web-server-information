<?php
/**
 * In-request collector for a plugin's own WordPress notices.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Records `_doing_it_wrong`, deprecation and early-i18n notices raised by one plugin.
 *
 * These are the loudest signal that something is wired wrongly, and the easiest
 * to miss: production runs with `WP_DEBUG` off, so nothing is displayed or
 * logged and the problem is invisible. Collecting them regardless means a
 * diagnostics report carries them even on a site configured to stay quiet.
 *
 * Extracted from wpheka-seo-os src/Tools/NoticeLog.php, and generalised: the
 * plugin root and slug are constructor arguments so several plugins can each
 * run their own collector on the same request without blaming one another.
 *
 * Scope is deliberately narrow. Only notices attributable to this plugin — by
 * its own files appearing in the call stack, or its slug appearing in the
 * message — are kept. Memory only, capped, never written to the database.
 */
final class NoticeLog {

	/**
	 * Most notices kept per request; a runaway loop must not exhaust memory.
	 */
	private const LIMIT = 25;

	/**
	 * Absolute, normalised plugin root directory.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Plugin slug, as it appears in an early-i18n notice message.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Collected notices for this request.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $notices = array();

	/**
	 * Construct a collector for one plugin.
	 *
	 * @param string $plugin_dir Plugin root directory, e.g. plugin_dir_path( __FILE__ ).
	 * @param string $slug       Plugin slug / text domain.
	 */
	public function __construct( string $plugin_dir, string $slug ) {
		$this->root = trailingslashit( wp_normalize_path( $plugin_dir ) );
		$this->slug = $slug;
	}

	/**
	 * Hook the collectors.
	 *
	 * Register this **first**, before the plugin's other subsystems, so it is
	 * already listening while they register themselves — which is exactly when
	 * boot-order mistakes surface.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 3 );
		add_action( 'deprecated_function_run', array( $this, 'record_deprecation' ), 10, 3 );
		add_action( 'deprecated_argument_run', array( $this, 'record_deprecated_argument' ), 10, 3 );
	}

	/**
	 * Record a `_doing_it_wrong()` call.
	 *
	 * @param string $function_name Function called incorrectly.
	 * @param string $message       Explanation.
	 * @param string $version       Version the message was added in.
	 * @return void
	 */
	public function record_doing_it_wrong( $function_name, $message = '', $version = '' ): void {
		$this->add( 'doing_it_wrong', (string) $function_name, (string) $message, (string) $version );
	}

	/**
	 * Record a deprecated function.
	 *
	 * `deprecated_function_run` passes a *replacement* — a bare function name —
	 * so the sentence around it is built here.
	 *
	 * @param string $thing       Deprecated function.
	 * @param string $replacement Suggested replacement.
	 * @param string $version     Version it was deprecated in.
	 * @return void
	 */
	public function record_deprecation( $thing, $replacement = '', $version = '' ): void {
		$message = $replacement ? sprintf( 'Use %s instead.', $replacement ) : '';

		$this->add( 'deprecated', (string) $thing, $message, (string) $version );
	}

	/**
	 * Record a deprecated argument.
	 *
	 * `deprecated_argument_run` passes a complete, translated sentence in the
	 * second position rather than a replacement name — WordPress' own
	 * `_deprecated_argument()` builds it before firing the hook. Wrapping it in
	 * "Use %s instead." produced entries like "Use This argument has been
	 * deprecated since 5.5.0. instead." in the diagnostics report, which reads as
	 * a bug in the report rather than the deprecation it was pointing at.
	 *
	 * @param string $thing   Function whose argument is deprecated.
	 * @param string $message Complete message from WordPress.
	 * @param string $version Version it was deprecated in.
	 * @return void
	 */
	public function record_deprecated_argument( $thing, $message = '', $version = '' ): void {
		$this->add( 'deprecated', (string) $thing, (string) $message, (string) $version );
	}

	/**
	 * Everything collected this request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		return $this->notices;
	}

	/**
	 * Store a notice if it belongs to this plugin.
	 *
	 * @param string $type    doing_it_wrong or deprecated.
	 * @param string $subject Function or thing named by the notice.
	 * @param string $message Explanation.
	 * @param string $version Version string from the notice.
	 * @return void
	 */
	private function add( string $type, string $subject, string $message, string $version ): void {
		if ( count( $this->notices ) >= self::LIMIT ) {
			return;
		}

		$frame = $this->blame( $message );

		if ( null === $frame ) {
			return;
		}

		$key = $type . '|' . $subject . '|' . $frame;

		foreach ( $this->notices as $existing ) {
			if ( $existing['key'] === $key ) {
				return; // Same notice from the same place: record it once.
			}
		}

		$this->notices[] = array(
			'key'      => $key,
			'type'     => $type,
			'subject'  => $subject,
			'message'  => wp_strip_all_tags( $message ),
			'version'  => $version,
			'source'   => $frame,

			/*
			 * Whether this happened before `init`, which is what the early-i18n
			 * notice is really about (ADR-007). WordPress 6.7+ refuses to
			 * translate strings requested this early, silently.
			 */
			'pre_init' => ! did_action( 'init' ),
		);
	}

	/**
	 * First call-stack frame inside this plugin, or null if the notice is not ours.
	 *
	 * The slug is checked as well as the stack, because the early-i18n notice
	 * names the text domain in its message while the offending frame can be
	 * anywhere.
	 *
	 * @param string $message Notice message.
	 * @return string|null Plugin-relative file:line, or null when not ours.
	 */
	private function blame( string $message ): ?string {
		/*
		 * The emptiness check is load-bearing. `strpos( $message, '' )` returns 0,
		 * and `false !== 0` is true, so a collector constructed with an empty slug
		 * claimed *every* notice raised anywhere on the site — filling a plugin's
		 * diagnostics report with other plugins' problems, and hitting LIMIT before
		 * the plugin's own notice arrived.
		 */
		$ours  = '' !== $this->slug && false !== strpos( $message, $this->slug );
		$self  = wp_normalize_path( __FILE__ );
		$frame = null;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_debug_backtrace, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $step ) {
			if ( empty( $step['file'] ) ) {
				continue;
			}

			$file = wp_normalize_path( (string) $step['file'] );

			/*
			 * This collector is bundled inside the plugin, so its own frames sit
			 * nearest the top of the stack. Skipping them is what makes the
			 * report name the real culprit rather than the messenger.
			 */
			if ( $file === $self ) {
				continue;
			}

			if ( 0 === strpos( $file, $this->root ) ) {
				$frame = str_replace( $this->root, '', $file ) . ':' . ( $step['line'] ?? 0 );
				break;
			}
		}

		if ( null !== $frame ) {
			return $frame;
		}

		// Ours by text domain, but raised entirely outside our files.
		return $ours ? '(outside plugin files)' : null;
	}
}
