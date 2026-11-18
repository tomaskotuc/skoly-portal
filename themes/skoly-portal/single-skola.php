<?php
/**
 * Detail školy.
 */
get_header();

while ( have_posts() ) :
	the_post();
	$skoly_mista = get_field( 'mista_vyuky' ) ?: array();
	$skoly_obory = get_field( 'obory' ) ?: array();
	?>
	<a class="back" href="<?php echo esc_url( get_post_type_archive_link( 'skola' ) ); ?>">‹ Zpět na výpis škol</a>
	<h1><?php the_title(); ?></h1>

	<div class="detail">
		<div>
			<section class="panel">
				<h2>Obory</h2>
				<?php if ( $skoly_obory ) : ?>
					<div class="table-scroll">
						<table class="obory">
							<thead>
								<tr><th>Kód</th><th>Název</th><th>Forma</th><th>Délka</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $skoly_obory as $skoly_obor ) : ?>
									<tr>
										<td><code><?php echo esc_html( $skoly_obor['kod'] ); ?></code></td>
										<td>
											<?php echo esc_html( $skoly_obor['nazev'] ); ?>
											<?php if ( $skoly_obor['dobihaji'] ) : ?>
												<br><span class="dobiha">dobíhající obor (nepřijímá nové žáky)</span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $skoly_obor['forma'] ); ?></td>
										<td><?php echo esc_html( $skoly_obor['delka'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="note">Forma a délka jsou zatím kódy z číselníků RAFS a RADS, převod na názvy bude doplněn.</p>
				<?php else : ?>
					<p>Škola nemá v rejstříku uvedené obory.</p>
				<?php endif; ?>
			</section>

			<section class="panel">
				<h2>Místa výuky</h2>
				<ul class="places">
					<?php foreach ( $skoly_mista as $skoly_misto ) : ?>
						<li><?php echo esc_html( $skoly_misto['adresa'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>

		<aside class="panel">
			<h2>Základní údaje</h2>
			<dl class="facts">
				<dt>Adresa sídla</dt>
				<dd><?php echo esc_html( get_field( 'adresa_sidla' ) ); ?></dd>
				<dt>Kraj</dt>
				<dd><?php echo esc_html( skoly_portal_terms( get_the_ID(), 'kraj' ) ); ?></dd>
				<dt>Okres</dt>
				<dd><?php echo esc_html( skoly_portal_terms( get_the_ID(), 'okres' ) ); ?></dd>
				<dt>Zřizovatel</dt>
				<dd><?php echo esc_html( skoly_portal_terms( get_the_ID(), 'zrizovatel' ) ); ?></dd>
				<dt>Typ studia</dt>
				<dd><?php echo esc_html( skoly_portal_terms( get_the_ID(), 'typ_studia' ) ); ?></dd>
				<dt>RED_IZO</dt>
				<dd><?php echo esc_html( get_field( 'red_izo' ) ); ?></dd>
				<dt>Datum aktualizace</dt>
				<dd><?php echo esc_html( get_field( 'datum_aktualizace' ) ); ?></dd>
			</dl>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
