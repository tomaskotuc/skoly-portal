	</div>
</main>
<footer class="site-footer">
	<div class="wrap">
		Data: Rejstřík škol a školských zařízení, MŠMT (<a href="https://data.gov.cz">data.gov.cz</a>)<?php
		$skoly_datum = get_option( 'skoly_import_datum_dat' );
		if ( $skoly_datum ) {
			echo ', stav k ' . esc_html( date_i18n( 'j. n. Y', strtotime( $skoly_datum ) ) );
		}
		?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
