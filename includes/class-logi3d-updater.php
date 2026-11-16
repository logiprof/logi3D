<?php
/**
 * Logi3D – mises à jour automatiques depuis GitHub.
 *
 * Le site lit la dernière « release » du dépôt public logiprof/logi3d.
 * Si sa version est plus récente que celle installée, WordPress propose
 * la mise à jour (et l'installe tout seul, les mises à jour automatiques
 * étant activées pour Logi3D).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logi3D_Updater {

	const REPO      = 'logiprof/logi3d';
	const SLUG      = 'logi3d';
	const ASSET     = 'logi3d.zip';
	const CACHE_KEY = 'logi3d_github_release';
	const CACHE_TTL = 21600; // 6 heures

	private $file;
	private $basename;
	private $version;

	public function __construct( $file, $version ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $file );
		$this->version  = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 10, 2 );
		add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links' ) );
		add_action( 'admin_post_logi3d_check_update', array( $this, 'force_check' ) );
	}

	/* Dernière release publiée sur GitHub (mise en cache 6 h). */
	public function release( $force = false ) {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( ! $force && false !== $cached ) {
			return $cached ? $cached : null;
		}
		$res = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Logi3D-Updater',
				),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			set_site_transient( self::CACHE_KEY, '', HOUR_IN_SECONDS ); // on réessaie dans 1 h
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $data['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, '', HOUR_IN_SECONDS );
			return null;
		}
		$package = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] ) {
					$package = $asset['browser_download_url'];
					break;
				}
			}
		}
		if ( '' === $package && ! empty( $data['zipball_url'] ) ) {
			$package = $data['zipball_url'];
		}
		$release = array(
			'version'   => ltrim( $data['tag_name'], 'vV' ),
			'package'   => $package,
			'url'       => isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . self::REPO,
			'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published' => isset( $data['published_at'] ) ? $data['published_at'] : '',
		);
		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	private function item( $release ) {
		return (object) array(
			'id'            => 'github.com/' . self::REPO,
			'slug'          => self::SLUG,
			'plugin'        => $this->basename,
			'new_version'   => $release ? $release['version'] : $this->version,
			'url'           => 'https://github.com/' . self::REPO,
			'package'       => $release ? $release['package'] : '',
			'requires'      => '5.8',
			'requires_php'  => '7.0',
			'icons'         => array(),
			'banners'       => array(),
			'compatibility' => new stdClass(),
		);
	}

	/* Ajoute Logi3D à la liste des mises à jour de WordPress. */
	public function check( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$release = $this->release();
		if ( $release && $release['package'] && version_compare( $release['version'], $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = $this->item( $release );
			if ( isset( $transient->no_update[ $this->basename ] ) ) {
				unset( $transient->no_update[ $this->basename ] );
			}
		} else {
			// Nécessaire pour que WordPress affiche l'option « mises à jour automatiques ».
			$transient->no_update[ $this->basename ] = $this->item( null );
			if ( isset( $transient->response[ $this->basename ] ) ) {
				unset( $transient->response[ $this->basename ] );
			}
		}
		return $transient;
	}

	/* Fenêtre « Afficher les détails » de la page Extensions. */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->release();
		$notes   = $release && '' !== $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '<p>Voir les notes de version sur GitHub.</p>';
		return (object) array(
			'name'          => 'Logi3D',
			'slug'          => self::SLUG,
			'version'       => $release ? $release['version'] : $this->version,
			'author'        => 'logiProf',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '5.8',
			'requires_php'  => '7.0',
			'last_updated'  => $release ? $release['published'] : '',
			'download_link' => $release ? $release['package'] : '',
			'sections'      => array(
				'description' => '<p>Animations pédagogiques 3D pour la logistique. Chaque animation a son code court, utilisable dans une page, un article ou une leçon Sensei.</p>',
				'changelog'   => $notes,
			),
		);
	}

	/* Si le paquet vient de l'archive automatique de GitHub, renomme le dossier en « logi3d ». */
	public function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}
		global $wp_filesystem;
		$wanted = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return $source;
	}

	public function flush( $upgrader, $options ) {
		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/* Mises à jour automatiques activées pour Logi3D. */
	public function auto_update( $update, $item ) {
		if ( isset( $item->plugin ) && $item->plugin === $this->basename ) {
			return true;
		}
		if ( isset( $item->slug ) && self::SLUG === $item->slug ) {
			return true;
		}
		return $update;
	}

	public function action_links( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=logi3d_check_update' ), 'logi3d_check_update' );
			$links[] = '<a href="' . esc_url( $url ) . '">Rechercher une mise à jour</a>';
		}
		return $links;
	}

	public function force_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'logi3d_check_update' );
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php?plugin_status=all' ) );
		exit;
	}
}
