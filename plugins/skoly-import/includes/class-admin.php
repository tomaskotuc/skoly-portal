<?php
defined( 'ABSPATH' ) || exit;

/**
 * Administrační stránka pluginu: nastavení zdroje, výsledek importu, ruční spuštění.
 */
class Skoly_Admin {

	const PAGE = 'skoly-import';

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_skoly_import_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_skoly_import_run', array( $this, 'handle_run' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=skola',
			'Import dat',
			'Import dat',
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url    = get_option( 'skoly_import_url', '' );
		$result = get_option( 'skoly_import_last_result' );
		?>
		<div class="wrap">
			<h1>Import dat z Rejstříku škol</h1>

			<?php if ( isset( $_GET['skoly_msg'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p>Akce byla provedena, výsledek je níže.</p></div>
			<?php endif; ?>

			<h2>Zdroj dat</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="skoly_import_save">
				<?php wp_nonce_field( 'skoly_import_save' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="skoly_import_url">URL datové sady kraje</label></th>
						<td><input type="url" class="large-text" id="skoly_import_url" name="skoly_import_url" value="<?php echo esc_attr( $url ); ?>"></td>
					</tr>
				</table>
				<?php submit_button( 'Uložit nastavení' ); ?>
			</form>

			<h2>Ruční import</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="skoly_import_run">
				<?php wp_nonce_field( 'skoly_import_run' ); ?>
				<?php submit_button( 'Spustit import', 'primary', 'submit', false ); ?>
			</form>

			<h2>Výsledek posledního importu</h2>
			<?php if ( ! $result ) : ?>
				<p>Import zatím neproběhl.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:600px">
					<tbody>
						<tr><th>Čas</th><td><?php echo esc_html( $result['time'] ); ?></td></tr>
						<tr><th>Stav</th><td><?php echo 'ok' === $result['status'] ? 'V pořádku' : 'Chyba'; ?></td></tr>
						<tr><th>Zpráva</th><td><?php echo esc_html( $result['message'] ); ?></td></tr>
						<tr><th>Nové školy</th><td><?php echo (int) $result['created']; ?></td></tr>
						<tr><th>Aktualizované</th><td><?php echo (int) $result['updated']; ?></td></tr>
						<tr><th>Přeskočené</th><td><?php echo (int) $result['skipped']; ?></td></tr>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save(): void {
		$this->check_request( 'skoly_import_save' );
		$url = isset( $_POST['skoly_import_url'] ) ? esc_url_raw( wp_unslash( $_POST['skoly_import_url'] ) ) : '';
		update_option( 'skoly_import_url', $url );
		$this->redirect_back();
	}

	public function handle_run(): void {
		$this->check_request( 'skoly_import_run' );
		$result = ( new Skoly_Importer() )->run( (string) get_option( 'skoly_import_url', '' ) );
		update_option( 'skoly_import_last_result', $result, false );
		$this->redirect_back();
	}

	private function check_request( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemáte oprávnění.' );
		}
		check_admin_referer( $action );
	}

	private function redirect_back(): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'skola',
					'page'      => self::PAGE,
					'skoly_msg' => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
