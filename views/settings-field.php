<input type="text" name="i12n_upload_path" id="i12n_upload_path" value="<?php echo esc_attr( $i12n_upload_path ); ?>" class="regular-text code">
<p class="description">
	<?php
	/* translators: %s: default upload path */
	printf( esc_html__( 'Default is %s' ), $default );
	?>
</p>
