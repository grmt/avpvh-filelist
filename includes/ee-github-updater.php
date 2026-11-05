<?php
/**
 * Plugin Name: GRMT GitHub & Custom Plugin Updater
 * Description: Automatically tracks updates and status for grmt GitHub and custom plugins in WordPress Dashboard & Updates.
 * Version:     1.1.0
 * Author:      grmt
 *
 * Works both inside a plugin and as a standalone Must-Use plugin (mu-plugins/grmt-github-updater.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'grmt_github_get_managed_repos' ) ) {

	/**
	 * Return mapping of known plugin directory slugs to GitHub `owner/repo`.
	 *
	 * @return array<string,string>
	 */
	function grmt_github_get_managed_repos() {
		return array(
			'simple-file-list'           => 'grmt/simple-file-list',
			'meili-search'               => 'grmt/meili-search',
			'print-my-blog'              => 'grmt/printmyblog',
			'wp-force-password-change'   => 'grmt/wp-force-password-change',
			'broken-link-checker'        => 'grmt/broken-link-checker',
			'geweb-ai-search'            => 'grmt/geweb-ai-search',
			'skaut-google-drive-gallery' => 'grmt/skaut-google-drive-gallery',
			'avpvh-bookkeeping'          => 'grmt/avpvh-bookkeeping',
			'avpvh-find-and-view'        => 'grmt/avpvh-find-and-view',
			'avpvh-gallery'              => 'grmt/avpvh-gallery',
			'avpvh-members'              => 'grmt/avpvh-members',
			'avpvh-print-map'            => 'grmt/avpvh-print-map',
			'avpvh-theme-fixes'          => 'grmt/avpvh-theme-fixes',
		);
	}

	/**
	 * Determine the plugin slug from a plugin basename (`folder/file.php` or `file.php`).
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string
	 */
	function grmt_github_get_plugin_slug( $plugin_file ) {
		$slug = dirname( $plugin_file );
		if ( '.' === $slug ) {
			$slug = basename( $plugin_file, '.php' );
		}
		return $slug;
	}

	/**
	 * Resolve the GitHub `owner/repo` for a plugin, or empty string if not GitHub-managed.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Plugin header data.
	 * @return string
	 */
	function grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data = array() ) {
		if ( ! empty( $plugin_data['UpdateURI'] ) ) {
			$host = wp_parse_url( $plugin_data['UpdateURI'], PHP_URL_HOST );
			$path = wp_parse_url( $plugin_data['UpdateURI'], PHP_URL_PATH );
			if ( 'github.com' === $host && is_string( $path ) ) {
				$candidate = trim( $path, '/' );
				if ( preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $candidate ) ) {
					return $candidate;
				}
			}
		}

		$slug  = grmt_github_get_plugin_slug( $plugin_file );
		$repos = grmt_github_get_managed_repos();
		if ( isset( $repos[ $slug ] ) ) {
			return $repos[ $slug ];
		}

		return '';
	}

	/**
	 * Check whether a plugin is a local custom plugin (not hosted on WordPress.org).
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Plugin header data.
	 * @return bool
	 */
	function grmt_github_is_custom_plugin( $plugin_file, $plugin_data = array() ) {
		if ( '' !== grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data ) ) {
			return true;
		}
		$slug = grmt_github_get_plugin_slug( $plugin_file );
		foreach ( array( 'avpvh-', 'geweb-', 'pvh-' ) as $prefix ) {
			if ( 0 === strpos( $slug, $prefix ) ) {
				return true;
			}
		}
		return in_array( $slug, array( 'celersearch' ), true );
	}

	/**
	 * Fetch and cache the latest GitHub release or tag metadata for `<owner>/<repo>`.
	 *
	 * @param string $repo_slug Repository in `owner/repo` format.
	 * @return array|false Normalized release array or false when no release/tag is public.
	 */
	function grmt_github_fetch_latest_release( $repo_slug ) {
		if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo_slug ) ) {
			return false;
		}

		$cache_key = 'grmt_gh_rel_' . md5( $repo_slug );
		$force     = isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $force ) {
			$cached = get_site_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return ! empty( $cached['tag_name'] ) ? $cached : false;
			}
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'WordPress-GitHub-Updater/' . get_bloginfo( 'version' ),
		);

		if ( defined( 'GRMT_GITHUB_TOKEN' ) && GRMT_GITHUB_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . GRMT_GITHUB_TOKEN;
		}

		// 1. Try latest published GitHub Release first.
		$release_url = 'https://api.github.com/repos/' . $repo_slug . '/releases/latest';
		$response    = wp_remote_get(
			$release_url,
			array(
				'timeout' => 8,
				'headers' => $headers,
			)
		);

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
				set_site_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
				return $data;
			}
		}

		// 2. Fallback to latest tag if repository uses tags without GitHub Releases.
		$tags_url      = 'https://api.github.com/repos/' . $repo_slug . '/tags?per_page=1';
		$tags_response = wp_remote_get(
			$tags_url,
			array(
				'timeout' => 8,
				'headers' => $headers,
			)
		);

		if ( ! is_wp_error( $tags_response ) && 200 === wp_remote_retrieve_response_code( $tags_response ) ) {
			$tags = json_decode( wp_remote_retrieve_body( $tags_response ), true );
			if ( is_array( $tags ) && ! empty( $tags[0]['name'] ) ) {
				$tag_name = (string) $tags[0]['name'];
				$data     = array(
					'tag_name'    => $tag_name,
					'html_url'    => 'https://github.com/' . $repo_slug . '/releases/tag/' . rawurlencode( $tag_name ),
					'zipball_url' => ! empty( $tags[0]['zipball_url'] ) ? $tags[0]['zipball_url'] : 'https://github.com/' . $repo_slug . '/archive/refs/tags/' . rawurlencode( $tag_name ) . '.zip',
					'body'        => '',
					'assets'      => array(),
				);
				set_site_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
				return $data;
			}
		}

		// Cache negative/private result for 30 minutes to avoid hammering GitHub API.
		set_site_transient( $cache_key, array(), 30 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Build a WordPress plugin update object for a GitHub-managed plugin.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Plugin headers.
	 * @param string $repo_slug   GitHub `owner/repo`.
	 * @return array{status:string,latest:string,update:object}|false
	 */
	function grmt_github_evaluate_plugin( $plugin_file, $plugin_data, $repo_slug ) {
		$release = grmt_github_fetch_latest_release( $repo_slug );
		if ( ! $release ) {
			return false;
		}

		$latest_version  = ltrim( (string) $release['tag_name'], 'vV' );
		$current_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '0.0.0';
		$plugin_slug     = grmt_github_get_plugin_slug( $plugin_file );

		// Prefer an attached `.zip` asset; fall back to `zipball_url` (renamed on install via upgrader_source_selection).
		$package_url = '';
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if (
					! empty( $asset['browser_download_url'] ) &&
					! empty( $asset['name'] ) &&
					'.zip' === substr( strtolower( $asset['name'] ), -4 )
				) {
					$package_url = $asset['browser_download_url'];
					if ( $asset['name'] === $plugin_slug . '.zip' ) {
						break;
					}
				}
			}
		}
		if ( ! $package_url && ! empty( $release['zipball_url'] ) ) {
			$package_url = $release['zipball_url'];
		}

		$update_obj = (object) array(
			'id'          => 'https://github.com/' . $repo_slug,
			'slug'        => $plugin_slug,
			'plugin'      => $plugin_file,
			'new_version' => $latest_version,
			'version'     => $latest_version,
			'url'         => isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . $repo_slug,
			'package'     => $package_url,
			'tested'      => get_bloginfo( 'version' ),
		);

		$has_update = ( '' !== $package_url && version_compare( $latest_version, $current_version, '>' ) );

		return array(
			'status' => $has_update ? 'update_available' : 'current',
			'latest' => $latest_version,
			'update' => $update_obj,
		);
	}

	/**
	 * Filter `update_plugins_github.com` for plugins with an explicit `Update URI: https://github.com/...`.
	 *
	 * @param array|false $update      The plugin update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	function grmt_github_plugin_update_check( $update, $plugin_data, $plugin_file ) {
		$repo_slug = grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data );
		if ( ! $repo_slug ) {
			return $update;
		}

		$eval = grmt_github_evaluate_plugin( $plugin_file, $plugin_data, $repo_slug );
		if ( ! $eval || 'update_available' !== $eval['status'] ) {
			return $update;
		}

		return (array) $eval['update'];
	}
	add_filter( 'update_plugins_github.com', 'grmt_github_plugin_update_check', 10, 3 );

	/**
	 * Inject updates for all mapped GitHub plugins and prevent WordPress.org from overwriting forks.
	 *
	 * @param object $transient The `update_plugins` site transient.
	 * @return object
	 */
	function grmt_github_inject_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( ! grmt_github_is_custom_plugin( $plugin_file, $plugin_data ) ) {
				continue;
			}

			// Always remove custom/forked plugins from any WP.org update response first.
			unset( $transient->response[ $plugin_file ] );

			$repo_slug = grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data );
			if ( ! $repo_slug ) {
				continue;
			}

			$eval = grmt_github_evaluate_plugin( $plugin_file, $plugin_data, $repo_slug );
			if ( ! $eval ) {
				continue;
			}

			if ( 'update_available' === $eval['status'] ) {
				$transient->response[ $plugin_file ] = $eval['update'];
				unset( $transient->no_update[ $plugin_file ] );
			} else {
				$transient->no_update[ $plugin_file ] = $eval['update'];
			}
		}

		return $transient;
	}
	add_filter( 'pre_set_site_transient_update_plugins', 'grmt_github_inject_plugin_updates', 20, 1 );

	/**
	 * Rename extracted GitHub zipball directories (e.g. `grmt-printmyblog-abc1234/`) to `<plugin-slug>/`.
	 *
	 * @param string      $source        File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 * @return string|WP_Error
	 */
	function grmt_github_fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) ) {
			return $source;
		}

		$plugin_file = $hook_extra['plugin'];
		if ( ! grmt_github_is_custom_plugin( $plugin_file ) ) {
			return $source;
		}

		$expected_slug = grmt_github_get_plugin_slug( $plugin_file );
		$corrected     = trailingslashit( $remote_source ) . trailingslashit( $expected_slug );

		if ( trailingslashit( $source ) === $corrected ) {
			return $source;
		}

		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $corrected, true ) ) {
			return $corrected;
		}

		return $source;
	}
	add_filter( 'upgrader_source_selection', 'grmt_github_fix_source_dir', 10, 4 );

	/**
	 * Populate the "View version X.Y.Z details" modal in wp-admin for GitHub-hosted plugins.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	function grmt_github_plugin_info_modal( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			$slug = grmt_github_get_plugin_slug( $plugin_file );
			if ( $slug !== $args->slug ) {
				continue;
			}

			$repo_slug = grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data );
			if ( ! $repo_slug ) {
				continue;
			}

			$release        = grmt_github_fetch_latest_release( $repo_slug );
			$latest_version = $release ? ltrim( (string) $release['tag_name'], 'vV' ) : $plugin_data['Version'];
			$changelog      = ( $release && ! empty( $release['body'] ) ) ? wpautop( esc_html( $release['body'] ) ) : '<p>Beheerd via GitHub repository <code>' . esc_html( $repo_slug ) . '</code>.</p>';

			return (object) array(
				'name'          => $plugin_data['Name'],
				'slug'          => $slug,
				'version'       => $latest_version,
				'author'        => $plugin_data['Author'],
				'homepage'      => 'https://github.com/' . $repo_slug,
				'Last_Updated'  => isset( $release['published_at'] ) ? $release['published_at'] : '',
				'sections'      => array(
					'description' => $plugin_data['Description'],
					'changelog'   => $changelog,
				),
				'download_link' => isset( $release['assets'][0]['browser_download_url'] ) ? $release['assets'][0]['browser_download_url'] : '',
			);
		}

		return $result;
	}
	add_filter( 'plugins_api', 'grmt_github_plugin_info_modal', 20, 3 );

	/**
	 * Add a GitHub repository badge to managed plugins on `wp-admin/plugins.php`.
	 *
	 * @param array  $plugin_meta Array of plugin row meta links.
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Plugin headers.
	 * @return array
	 */
	function grmt_github_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data ) {
		$repo_slug = grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data );
		if ( $repo_slug ) {
			$plugin_meta[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">GitHub: %s</a>',
				esc_url( 'https://github.com/' . $repo_slug ),
				esc_html( $repo_slug )
			);
		}
		return $plugin_meta;
	}
	add_filter( 'plugin_row_meta', 'grmt_github_plugin_row_meta', 10, 3 );

	/**
	 * Register the WordPress Admin Dashboard Widget showing all plugins (custom, GitHub & WordPress.org).
	 */
	function grmt_github_register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'grmt_github_plugins_status_widget',
			'Plugins Status (GitHub & WordPress.org)',
			'grmt_github_render_dashboard_widget'
		);
	}
	add_action( 'wp_dashboard_setup', 'grmt_github_register_dashboard_widget' );

	/**
	 * Render the WordPress Admin Dashboard Widget for all installed plugins.
	 */
	function grmt_github_render_dashboard_widget() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins          = get_plugins();
		$update_transient = get_site_transient( 'update_plugins' );
		$rows             = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$slug      = grmt_github_get_plugin_slug( $plugin_file );
			$repo_slug = grmt_github_get_repo_for_plugin( $plugin_file, $plugin_data );
			$is_custom = grmt_github_is_custom_plugin( $plugin_file, $plugin_data );
			$is_active = is_plugin_active( $plugin_file );
			$ver       = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '?';
			$status    = 'Actueel';
			$color     = '#15803d';
			$src_html  = 'Lokaal';

			if ( $repo_slug ) {
				$src_html = '<a href="' . esc_url( 'https://github.com/' . $repo_slug ) . '" target="_blank" rel="noopener">' . esc_html( $repo_slug ) . '</a>';
				$eval     = grmt_github_evaluate_plugin( $plugin_file, $plugin_data, $repo_slug );
				if ( $eval && 'update_available' === $eval['status'] ) {
					$status = 'Update: ' . $eval['latest'];
					$color  = '#b45309';
				} elseif ( ! $eval ) {
					$status = 'Git (actueel)';
					$color  = '#15803d';
				}
			} elseif ( $is_custom ) {
				$src_html = 'Lokaal';
				$status   = 'Lokaal beheerd';
				$color    = '#475569';
			} else {
				$src_html = '<a href="' . esc_url( 'https://wordpress.org/plugins/' . $slug . '/' ) . '" target="_blank" rel="noopener">wordpress.org</a>';
				if ( is_object( $update_transient ) && isset( $update_transient->response[ $plugin_file ] ) ) {
					$wp_upd = $update_transient->response[ $plugin_file ];
					$new_v  = is_object( $wp_upd ) && ! empty( $wp_upd->new_version ) ? $wp_upd->new_version : '?';
					$status = 'Update: ' . $new_v;
					$color  = '#b45309';
				}
			}

			$rows[] = array(
				'slug'     => $slug,
				'name'     => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $slug,
				'version'  => $ver,
				'active'   => $is_active,
				'src_html' => $src_html,
				'status'   => $status,
				'color'    => $color,
			);
		}

		if ( empty( $rows ) ) {
			echo '<p>Geen plugins geïnstalleerd op deze site.</p>';
			return;
		}

		echo '<table class="widefat striped" style="border:none;box-shadow:none;margin-bottom:8px;">';
		echo '<thead><tr><th>Plugin</th><th>Versie</th><th>Bron</th><th>Status</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$badge = $row['active'] ? '<span style="color:#15803d;" title="Actief">&#9679;</span> ' : '<span style="color:#94a3b8;" title="Inactief">&#9675;</span> ';

			echo '<tr>';
			echo '<td>' . wp_kses_post( $badge ) . '<strong>' . esc_html( $row['name'] ) . '</strong><br><code style="font-size:11px;">' . esc_html( $row['slug'] ) . '</code></td>';
			echo '<td><code>' . esc_html( $row['version'] ) . '</code></td>';
			echo '<td>' . wp_kses_post( $row['src_html'] ) . '</td>';
			echo '<td><strong style="color:' . esc_attr( $row['color'] ) . ';">' . esc_html( $row['status'] ) . '</strong></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin:0;text-align:right;"><a href="' . esc_url( admin_url( 'update-core.php?force-check=1' ) ) . '" class="button button-small">Controleer op updates</a></p>';
	}
}
