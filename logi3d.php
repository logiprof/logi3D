<?php
/**
 * Plugin Name: Logi3D
 * Description: Animations pédagogiques 3D pour la logistique (fichiers HTML). Chaque animation a son code court, utilisable dans une page, un article ou une leçon Sensei. Nouvelles animations : menu « Logi3D » → téléverser un fichier .html.
 * Version: 1.5.0
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * License: GPL-2.0-or-later
 * Text Domain: logi3d
 * Author: logiProf
 * Plugin URI: https://github.com/logiprof/logi3d
 * Update URI: https://github.com/logiprof/logi3d
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOGI3D_VERSION', '1.5.0' );
define( 'LOGI3D_FILE', __FILE__ );
define( 'LOGI3D_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOGI3D_URL', plugin_dir_url( __FILE__ ) );
define( 'LOGI3D_OPTION', 'logi3d_animations' );

/* Mises à jour automatiques depuis GitHub (dépôt logiprof/logi3d). */
require_once LOGI3D_DIR . 'includes/class-logi3d-updater.php';
new Logi3D_Updater( LOGI3D_FILE, LOGI3D_VERSION );

/* ------------------------------------------------------------------
 * Stockage
 * Animations incluses : dossier animations/ de l'extension.
 * Animations ajoutées : wp-content/uploads/logi3d/
 * (ce dossier est conservé lors des mises à jour de l'extension).
 * ------------------------------------------------------------------ */
function logi3d_upload_base() {
	$u = wp_upload_dir();
	return array(
		'dir' => trailingslashit( $u['basedir'] ) . 'logi3d/',
		'url' => trailingslashit( $u['baseurl'] ) . 'logi3d/',
	);
}

function logi3d_read_title( $path, $fallback ) {
	$head = @file_get_contents( $path, false, null, 0, 8192 );
	if ( $head && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $head, $m ) ) {
		$t = trim( wp_strip_all_tags( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' !== $t ) {
			return $t;
		}
	}
	return $fallback;
}

function logi3d_get_animations() {
	$list  = array();
	$files = glob( LOGI3D_DIR . 'animations/*.html' );
	if ( $files ) {
		foreach ( $files as $f ) {
			$slug          = sanitize_title( basename( $f, '.html' ) );
			$list[ $slug ] = array(
				'title'   => logi3d_read_title( $f, $slug ),
				'path'    => $f,
				'url'     => LOGI3D_URL . 'animations/' . basename( $f ),
				'source'  => 'incluse',
				'updated' => filemtime( $f ),
			);
		}
	}
	$base  = logi3d_upload_base();
	$saved = get_option( LOGI3D_OPTION, array() );
	if ( is_array( $saved ) ) {
		foreach ( $saved as $slug => $a ) {
			$path = $base['dir'] . $slug . '.html';
			if ( file_exists( $path ) ) {
				$list[ $slug ] = array(
					'title'   => ! empty( $a['title'] ) ? $a['title'] : $slug,
					'path'    => $path,
					'url'     => $base['url'] . $slug . '.html',
					'source'  => 'ajoutee',
					'updated' => filemtime( $path ),
				);
			}
		}
	}
	ksort( $list );
	return $list;
}

function logi3d_tag_for( $slug ) {
	return 'logi3d_' . str_replace( '-', '_', $slug );
}

/* ------------------------------------------------------------------
 * Codes courts
 * [animation id="surfaces-volumes"]  ou  [animation_surfaces_volumes]
 * Options : hauteur="900"  logo="non"
 * ------------------------------------------------------------------ */
function logi3d_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'             => '',
			'hauteur'        => '900',
			'largeur'        => '',
			'pleine_largeur' => '',
			'logo'           => 'oui',
		),
		$atts,
		'logi3d'
	);
	$slug = sanitize_title( $atts['id'] );
	$list = logi3d_get_animations();
	if ( ! isset( $list[ $slug ] ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return '<p style="color:#b32d2e;font-weight:600">Animation « ' . esc_html( $atts['id'] ) . ' » introuvable. Vérifie l\'identifiant dans le menu Logi3D.</p>';
		}
		return '';
	}
	$a    = $list[ $slug ];
	$h    = max( 200, intval( $atts['hauteur'] ) );
	$src  = add_query_arg( 'v', $a['updated'], $a['url'] );

	wp_enqueue_script( 'logi3d-front', LOGI3D_URL . 'assets/logi3d-front.js', array(), LOGI3D_VERSION, true );

	$wrap_style = 'display:block;position:relative;box-sizing:border-box;width:100%;max-width:100%;margin-left:auto;margin-right:auto;padding:0;float:none;';

	return '<div class="logi3d-anim" style="' . esc_attr( $wrap_style ) . '">'
		. '<iframe class="logi3d-anim-frame" src="' . esc_url( $src ) . '" title="' . esc_attr( $a['title'] ) . '" loading="lazy" allow="fullscreen" allowfullscreen scrolling="no"'
		. ' style="display:block;width:100%;max-width:none;height:' . $h . 'px;border:0;margin:0;padding:0;background:transparent;"></iframe>'
		. ( in_array( strtolower( trim( $atts['logo'] ) ), array( 'non', 'no', '0', 'false' ), true ) ? '' : logi3d_logo_html() )
		. '</div>';
}

function logi3d_register_shortcodes() {
	add_shortcode( 'logi3d', 'logi3d_shortcode' );
	foreach ( array_keys( logi3d_get_animations() ) as $slug ) {
		$tag = logi3d_tag_for( $slug );
		if ( ! shortcode_exists( $tag ) ) {
			add_shortcode(
				$tag,
				function ( $atts ) use ( $slug ) {
					$atts       = is_array( $atts ) ? $atts : array();
					$atts['id'] = $slug;
					return logi3d_shortcode( $atts );
				}
			);
		}
	}
}
add_action( 'init', 'logi3d_register_shortcodes' );

/* Anciens codes courts ([animation …], [animation_…]) : toujours acceptés. */
function logi3d_register_legacy() {
	if ( ! shortcode_exists( 'animation' ) ) {
		add_shortcode( 'animation', 'logi3d_shortcode' );
	}
	foreach ( array_keys( logi3d_get_animations() ) as $slug ) {
		$tag = 'animation_' . str_replace( '-', '_', $slug );
		if ( ! shortcode_exists( $tag ) ) {
			add_shortcode(
				$tag,
				function ( $atts ) use ( $slug ) {
					$atts       = is_array( $atts ) ? $atts : array();
					$atts['id'] = $slug;
					return logi3d_shortcode( $atts );
				}
			);
		}
	}
}
add_action( 'init', 'logi3d_register_legacy', 20 );

/* Reprend les animations ajoutées avec l'ancienne extension « Animations pédagogiques ». */
function logi3d_migrate() {
	if ( get_option( 'logi3d_migrated' ) ) {
		return;
	}
	$old = get_option( 'ap_animations', array() );
	if ( is_array( $old ) && $old ) {
		$u       = wp_upload_dir();
		$old_dir = trailingslashit( $u['basedir'] ) . 'animations-pedagogiques/';
		$base    = logi3d_upload_base();
		wp_mkdir_p( $base['dir'] );
		$saved = get_option( LOGI3D_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( $old as $slug => $a ) {
			if ( file_exists( $old_dir . $slug . '.html' ) && ! file_exists( $base['dir'] . $slug . '.html' ) ) {
				@copy( $old_dir . $slug . '.html', $base['dir'] . $slug . '.html' );
			}
			if ( ! isset( $saved[ $slug ] ) ) {
				$saved[ $slug ] = $a;
			}
		}
		update_option( LOGI3D_OPTION, $saved, false );
	}
	update_option( 'logi3d_migrated', 1, false );
}
add_action( 'admin_init', 'logi3d_migrate' );

/* Empêche les extensions de cache de retarder ou fusionner le script. */
function logi3d_script_tag( $tag, $handle ) {
	if ( 'logi3d-front' === $handle ) {
		$tag = str_replace( ' src=', ' data-no-optimize="1" data-no-defer="1" data-cfasync="false" nowprocket src=', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'logi3d_script_tag', 10, 2 );


/* ------------------------------------------------------------------
 * Logo en surcouche (réglage commun à toutes les animations)
 * ------------------------------------------------------------------ */
function logi3d_logo_settings() {
	$d = array( 'id' => 0, 'pos' => 'haut-droite', 'width' => 130, 'opacity' => 90, 'link' => '' );
	$s = get_option( 'logi3d_logo', array() );
	return array_merge( $d, is_array( $s ) ? $s : array() );
}

function logi3d_logo_html() {
	$o = logi3d_logo_settings();
	if ( empty( $o['id'] ) ) {
		return '';
	}
	$src = wp_get_attachment_image_url( (int) $o['id'], 'full' );
	if ( ! $src ) {
		return '';
	}
	$alt = get_post_meta( (int) $o['id'], '_wp_attachment_image_alt', true );
	$alt = $alt ? $alt : 'Logo';
	$gap = 'calc(14px + 0.5vw)';
	$map = array(
		'haut-droite' => 'top:' . $gap . ';right:' . $gap . ';',
		'haut-gauche' => 'top:' . $gap . ';left:' . $gap . ';',
		'bas-droite'  => 'bottom:' . $gap . ';right:' . $gap . ';',
		'bas-gauche'  => 'bottom:' . $gap . ';left:' . $gap . ';',
	);
	$place   = isset( $map[ $o['pos'] ] ) ? $map[ $o['pos'] ] : $map['haut-droite'];
	$w       = max( 30, min( 600, (int) $o['width'] ) );
	$op      = max( 10, min( 100, (int) $o['opacity'] ) ) / 100;
	$box     = 'position:absolute!important;' . $place . 'z-index:5;display:block;line-height:0;margin:0!important;padding:0!important;border:0!important;'
		. 'width:' . $w . 'px;max-width:22%;opacity:' . $op . ';';
	$img_css = 'display:block!important;width:100%!important;height:auto!important;max-width:100%!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important;border-radius:0!important;';
	$img     = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" style="' . $img_css . '" loading="lazy" decoding="async">';
	if ( ! empty( $o['link'] ) ) {
		return '<a class="logi3d-logo" href="' . esc_url( $o['link'] ) . '" target="_blank" rel="noopener" style="' . esc_attr( $box ) . 'pointer-events:auto;">' . $img . '</a>';
	}
	return '<span class="logi3d-logo" aria-hidden="false" style="' . esc_attr( $box ) . 'pointer-events:none;">' . $img . '</span>';
}

function logi3d_handle_logo() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'logi3d_logo' );
	$pos = isset( $_POST['logi3d_logo_pos'] ) ? sanitize_key( wp_unslash( $_POST['logi3d_logo_pos'] ) ) : 'haut-droite';
	$o   = array(
		'id'      => isset( $_POST['logi3d_logo_id'] ) ? absint( $_POST['logi3d_logo_id'] ) : 0,
		'pos'     => in_array( $pos, array( 'haut-droite', 'haut-gauche', 'bas-droite', 'bas-gauche' ), true ) ? $pos : 'haut-droite',
		'width'   => isset( $_POST['logi3d_logo_width'] ) ? max( 30, min( 600, absint( $_POST['logi3d_logo_width'] ) ) ) : 130,
		'opacity' => isset( $_POST['logi3d_logo_opacity'] ) ? max( 10, min( 100, absint( $_POST['logi3d_logo_opacity'] ) ) ) : 90,
		'link'    => isset( $_POST['logi3d_logo_link'] ) ? esc_url_raw( wp_unslash( $_POST['logi3d_logo_link'] ) ) : '',
	);
	update_option( 'logi3d_logo', $o, false );
	logi3d_redirect( $o['id'] ? 'Logo enregistré. Il s\'affiche sur toutes les animations.' : 'Logo retiré des animations.' );
}
add_action( 'admin_post_logi3d_logo', 'logi3d_handle_logo' );



function logi3d_admin_assets( $hook ) {
	if ( 'toplevel_page_logi3d' === $hook ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'logi3d_admin_assets' );

/* ------------------------------------------------------------------
 * Administration : menu « Logi3D »
 * ------------------------------------------------------------------ */
function logi3d_admin_menu() {
	add_menu_page( 'Logi3D – Animations pédagogiques', 'Logi3D', 'manage_options', 'logi3d', 'logi3d_admin_page', 'dashicons-video-alt3', 58 );
}
add_action( 'admin_menu', 'logi3d_admin_menu' );

function logi3d_redirect( $msg, $type = 'success' ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'logi3d', 'logi3d_msg' => rawurlencode( $msg ), 'logi3d_type' => $type ), admin_url( 'admin.php' ) ) );
	exit;
}

function logi3d_handle_upload() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'logi3d_upload' );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		logi3d_redirect( 'Ton compte n\'a pas le droit d\'ajouter du HTML avec des scripts (droit « unfiltered_html »). Utilise un compte administrateur.', 'error' );
	}
	if ( empty( $_FILES['logi3d_file'] ) || UPLOAD_ERR_OK !== $_FILES['logi3d_file']['error'] ) {
		logi3d_redirect( 'Aucun fichier reçu. Choisis un fichier .html.', 'error' );
	}
	$file = $_FILES['logi3d_file'];
	$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'html', 'htm' ), true ) ) {
		logi3d_redirect( 'Le fichier doit être un .html.', 'error' );
	}
	if ( $file['size'] > 20 * 1024 * 1024 ) {
		logi3d_redirect( 'Le fichier dépasse 20 Mo.', 'error' );
	}
	$head = file_get_contents( $file['tmp_name'], false, null, 0, 4096 );
	if ( false === $head || ! preg_match( '/<(!doctype|html)/i', $head ) ) {
		logi3d_redirect( 'Ce fichier ne ressemble pas à une page HTML complète.', 'error' );
	}

	$title = isset( $_POST['logi3d_title'] ) ? sanitize_text_field( wp_unslash( $_POST['logi3d_title'] ) ) : '';
	$slug  = isset( $_POST['logi3d_slug'] ) ? sanitize_title( wp_unslash( $_POST['logi3d_slug'] ) ) : '';
	if ( '' === $title ) {
		$title = logi3d_read_title( $file['tmp_name'], pathinfo( $file['name'], PATHINFO_FILENAME ) );
	}
	if ( '' === $slug ) {
		$slug = sanitize_title( $title );
	}
	if ( '' === $slug ) {
		logi3d_redirect( 'Impossible de créer un identifiant. Remplis le champ « Identifiant ».', 'error' );
	}

	$base = logi3d_upload_base();
	if ( ! wp_mkdir_p( $base['dir'] ) ) {
		logi3d_redirect( 'Impossible de créer le dossier ' . $base['dir'], 'error' );
	}
	if ( ! file_exists( $base['dir'] . 'index.php' ) ) {
		@file_put_contents( $base['dir'] . 'index.php', "<?php\n// Silence.\n" );
	}
	$existed = isset( logi3d_get_animations()[ $slug ] );
	if ( ! @move_uploaded_file( $file['tmp_name'], $base['dir'] . $slug . '.html' ) ) {
		logi3d_redirect( 'Le fichier n\'a pas pu être enregistré sur le serveur.', 'error' );
	}
	$saved          = get_option( LOGI3D_OPTION, array() );
	$saved          = is_array( $saved ) ? $saved : array();
	$saved[ $slug ] = array( 'title' => $title, 'updated' => time() );
	update_option( LOGI3D_OPTION, $saved, false );

	logi3d_redirect( ( $existed ? 'Animation remplacée : ' : 'Animation ajoutée : ' ) . $title . '. Code court : [' . logi3d_tag_for( $slug ) . ']' );
}
add_action( 'admin_post_logi3d_upload', 'logi3d_handle_upload' );

function logi3d_handle_delete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	$slug = isset( $_POST['logi3d_slug'] ) ? sanitize_title( wp_unslash( $_POST['logi3d_slug'] ) ) : '';
	check_admin_referer( 'logi3d_delete_' . $slug );
	$base = logi3d_upload_base();
	$path = $base['dir'] . $slug . '.html';
	if ( '' !== $slug && file_exists( $path ) ) {
		@unlink( $path );
	}
	$saved = get_option( LOGI3D_OPTION, array() );
	if ( is_array( $saved ) && isset( $saved[ $slug ] ) ) {
		unset( $saved[ $slug ] );
		update_option( LOGI3D_OPTION, $saved, false );
	}
	logi3d_redirect( 'Animation supprimée.' );
}
add_action( 'admin_post_logi3d_delete', 'logi3d_handle_delete' );

function logi3d_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$list = logi3d_get_animations();
	?>
	<div class="wrap">
		<h1>Logi3D – Animations pédagogiques</h1>

		<?php if ( ! empty( $_GET['logi3d_msg'] ) ) : ?>
			<div class="notice notice-<?php echo ( isset( $_GET['logi3d_type'] ) && 'error' === $_GET['logi3d_type'] ) ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( wp_unslash( rawurldecode( $_GET['logi3d_msg'] ) ) ); ?></p>
			</div>
		<?php endif; ?>

		<p>Copie le code court d'une animation et colle-le dans un bloc <strong>« Code court »</strong> de ta page, de ton article ou de ta leçon Sensei.</p>

		<table class="widefat striped" style="max-width:1100px">
			<thead><tr><th>Animation</th><th>Code court</th><th>Source</th><th>Mise à jour</th><th>Actions</th></tr></thead>
			<tbody>
			<?php if ( empty( $list ) ) : ?>
				<tr><td colspan="5">Aucune animation pour l'instant.</td></tr>
			<?php endif; ?>
			<?php foreach ( $list as $slug => $a ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $a['title'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
					<td>
						<input type="text" readonly class="regular-text" onclick="this.select()" value="<?php echo esc_attr( '[' . logi3d_tag_for( $slug ) . ']' ); ?>"><br>
						<input type="text" readonly class="regular-text" onclick="this.select()" value="<?php echo esc_attr( '[logi3d id="' . $slug . '"]' ); ?>" style="margin-top:4px">
					</td>
					<td><?php echo 'incluse' === $a['source'] ? 'Incluse' : 'Ajoutée'; ?></td>
					<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', $a['updated'] ) ); ?></td>
					<td>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'v', $a['updated'], $a['url'] ) ); ?>" target="_blank" rel="noopener">Voir</a>
						<?php if ( 'ajoutee' === $a['source'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Supprimer cette animation ? Les codes courts déjà placés n\'afficheront plus rien.');">
								<input type="hidden" name="action" value="logi3d_delete">
								<input type="hidden" name="logi3d_slug" value="<?php echo esc_attr( $slug ); ?>">
								<?php wp_nonce_field( 'logi3d_delete_' . $slug ); ?>
								<button type="submit" class="button button-link-delete">Supprimer</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2 style="margin-top:32px">Ajouter ou remplacer une animation</h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="logi3d_upload">
			<?php wp_nonce_field( 'logi3d_upload' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="logi3d_file">Fichier de l'animation (.html)</label></th>
					<td><input type="file" name="logi3d_file" id="logi3d_file" accept=".html,.htm" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_title">Titre</label></th>
					<td><input type="text" name="logi3d_title" id="logi3d_title" class="regular-text" placeholder="Facultatif : repris du fichier si vide"></td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_slug">Identifiant</label></th>
					<td>
						<input type="text" name="logi3d_slug" id="logi3d_slug" class="regular-text" placeholder="ex. gestion-des-stocks">
						<p class="description">Il donne le code court : <code>gestion-des-stocks</code> → <code>[logi3d_gestion_des_stocks]</code>.<br>
						Pour <strong>mettre à jour</strong> une animation, réutilise son identifiant : le fichier est remplacé et les codes courts déjà placés affichent la nouvelle version.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Téléverser l\'animation' ); ?>
		</form>

		<h2 style="margin-top:32px">Logo en surcouche</h2>
		<?php
		$lo      = logi3d_logo_settings();
		$lo_src  = $lo['id'] ? wp_get_attachment_image_url( (int) $lo['id'], 'medium' ) : '';
		?>
		<p>Le logo choisi s'affiche par-dessus <strong>toutes</strong> les animations. Pour le cacher sur une seule animation : <code>[logi3d_surfaces_volumes logo="non"]</code>.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="logi3d_logo">
			<?php wp_nonce_field( 'logi3d_logo' ); ?>
			<input type="hidden" name="logi3d_logo_id" id="logi3d_logo_id" value="<?php echo esc_attr( $lo['id'] ? $lo['id'] : '' ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Logo</th>
					<td>
						<div style="background:#edf0f4;border:1px solid #dcdcde;border-radius:6px;padding:12px;display:inline-block;min-width:160px;min-height:40px;margin-bottom:8px">
							<img id="logi3d-logo-preview" src="<?php echo esc_url( $lo_src ); ?>" alt="" style="max-width:200px;max-height:100px;<?php echo $lo_src ? '' : 'display:none;'; ?>">
							<span id="logi3d-logo-empty" style="color:#646970;<?php echo $lo_src ? 'display:none;' : ''; ?>">Aucun logo</span>
						</div><br>
						<button type="button" class="button" id="logi3d-logo-pick">Choisir dans la médiathèque</button>
						<button type="button" class="button-link-delete" id="logi3d-logo-remove" style="margin-left:8px;<?php echo $lo_src ? '' : 'display:none;'; ?>">Retirer le logo</button>
						<p class="description">Un PNG ou SVG à fond transparent donne le meilleur rendu.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_logo_pos">Position</label></th>
					<td>
						<select name="logi3d_logo_pos" id="logi3d_logo_pos">
							<?php
							foreach ( array( 'haut-droite' => 'En haut à droite', 'haut-gauche' => 'En haut à gauche', 'bas-droite' => 'En bas à droite', 'bas-gauche' => 'En bas à gauche' ) as $k => $label ) {
								echo '<option value="' . esc_attr( $k ) . '"' . selected( $lo['pos'], $k, false ) . '>' . esc_html( $label ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_logo_width">Largeur</label></th>
					<td><input type="number" min="30" max="600" step="5" name="logi3d_logo_width" id="logi3d_logo_width" value="<?php echo esc_attr( $lo['width'] ); ?>" class="small-text"> px <span class="description">(réduit automatiquement sur téléphone)</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_logo_opacity">Opacité</label></th>
					<td><input type="number" min="10" max="100" step="5" name="logi3d_logo_opacity" id="logi3d_logo_opacity" value="<?php echo esc_attr( $lo['opacity'] ); ?>" class="small-text"> %</td>
				</tr>
				<tr>
					<th scope="row"><label for="logi3d_logo_link">Lien (facultatif)</label></th>
					<td><input type="url" name="logi3d_logo_link" id="logi3d_logo_link" value="<?php echo esc_attr( $lo['link'] ); ?>" class="regular-text" placeholder="https://"><p class="description">Si rempli, un clic sur le logo ouvre ce lien dans un nouvel onglet.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Enregistrer le logo' ); ?>
		</form>
		<script>
		jQuery(function ($) {
			var frame;
			$('#logi3d-logo-pick').on('click', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({ title: 'Choisir le logo', button: { text: 'Utiliser ce logo' }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					$('#logi3d_logo_id').val(a.id);
					$('#logi3d-logo-preview').attr('src', (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url)).show();
					$('#logi3d-logo-empty').hide();
					$('#logi3d-logo-remove').show();
				});
				frame.open();
			});
			$('#logi3d-logo-remove').on('click', function (e) {
				e.preventDefault();
				$('#logi3d_logo_id').val('');
				$('#logi3d-logo-preview').hide();
				$('#logi3d-logo-empty').show();
				$(this).hide();
			});
		});
		</script>

		<h2>Options du code court</h2>
		<p>L'animation prend toujours la largeur du contenu de la page ou de la leçon, comme le texte.<br>
		<code>[logi3d id="surfaces-volumes" hauteur="1200"]</code> : hauteur de départ en pixels (elle s'ajuste ensuite toute seule au contenu).<br>
		<code>[logi3d id="surfaces-volumes" logo="non"]</code> : cache le logo sur cette animation.</p>
	</div>
	<?php
}
