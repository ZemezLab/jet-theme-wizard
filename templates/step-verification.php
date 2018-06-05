<?php
/**
 * Verification Step template
 */
?>
<h2><?php esc_html_e( 'Install Theme', 'jet-theme-wizard' ); ?></h2>
<div class="desc"><?php
	esc_html_e( 'Please, enter your license key to start installation:', 'jet-theme-wizard' );
?></div>
<div class="theme-wizard-form">
	<?php
		jet_theme_interface()->add_form_row( array(
			'label'       => esc_html__( 'License Key:', 'jet-theme-wizard' ),
			'field'       => 'license_key',
			'placeholder' => esc_html__( 'Enter your license key here...', 'jet-theme-wizard' ),
		) );
		jet_theme_interface()->button( array(
			'action' => 'start-install',
			'text'   => esc_html__( 'Start Install', 'jet-theme-wizard' ),
		) );
	?>
</div>