<?php
/**
 * Výpis škol s filtry (používá ho i taxonomy.php pro adresy typu /okres/blansko/).
 */
get_header();

global $wp_query;
?>
<h1>Střední školy</h1>
<p class="lead">Vyberte kraj, okres, skupinu oborů, zřizovatele nebo typ studia. Filtry se kombinují.</p>

<form class="filters" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'skola' ) ); ?>">
	<?php foreach ( skoly_portal_filters() as $skoly_tax => $skoly_label ) : ?>
		<?php
		$skoly_args = array(
			'taxonomy'   => $skoly_tax,
			'hide_empty' => true,
			'orderby'    => 'name',
		);
		if ( 'obor' === $skoly_tax ) {
			$skoly_args['parent'] = 0; // jen skupiny oborů, filtr zahrne i jejich obory
		}
		$skoly_terms   = get_terms( $skoly_args );
		$skoly_current = (string) get_query_var( $skoly_tax );
		?>
		<div>
			<label for="f-<?php echo esc_attr( $skoly_tax ); ?>"><?php echo esc_html( $skoly_label ); ?></label>
			<select id="f-<?php echo esc_attr( $skoly_tax ); ?>" name="<?php echo esc_attr( $skoly_tax ); ?>">
				<option value="">Vše</option>
				<?php foreach ( $skoly_terms as $skoly_term ) : ?>
					<option value="<?php echo esc_attr( $skoly_term->slug ); ?>" <?php selected( $skoly_current, $skoly_term->slug ); ?>>
						<?php echo esc_html( $skoly_term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endforeach; ?>
	<div class="actions">
		<button type="submit" class="button">Filtrovat</button>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'skola' ) ); ?>">Zrušit filtry</a>
	</div>
</form>

<p class="result-count">Nalezeno škol: <?php echo (int) $wp_query->found_posts; ?></p>

<?php if ( have_posts() ) : ?>
	<div class="cards">
		<?php
		while ( have_posts() ) :
			the_post();
			$skoly_obory = get_field( 'obory' ) ?: array();
			$skoly_typy  = wp_get_post_terms( get_the_ID(), 'typ_studia', array( 'fields' => 'names' ) );
			?>
			<article class="card">
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="meta">
					Okres: <?php echo esc_html( skoly_portal_terms( get_the_ID(), 'okres' ) ); ?><br>
					Zřizovatel: <?php echo esc_html( skoly_portal_terms( get_the_ID(), 'zrizovatel' ) ); ?><br>
					Počet oborů: <?php echo count( array_unique( array_column( $skoly_obory, 'kod' ) ) ); ?>
				</p>
				<?php if ( $skoly_typy && ! is_wp_error( $skoly_typy ) ) : ?>
					<ul class="tags">
						<?php foreach ( $skoly_typy as $skoly_typ ) : ?>
							<li class="tag"><?php echo esc_html( $skoly_typ ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
	</div>

	<nav class="pagination">
		<?php
		echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
			array(
				'prev_text' => '‹ Předchozí',
				'next_text' => 'Další ›',
			)
		);
		?>
	</nav>
<?php else : ?>
	<p>Zadaným filtrům neodpovídá žádná škola.</p>
<?php endif; ?>

<?php
get_footer();
