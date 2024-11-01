<h2><?php _e( 'Identification', 'user-identification' ); ?></h2>
<table class="form-table">
	<tr>
		<th><label for="i12n"><?php _e( 'Attached file', 'user-identification' ); ?></label></th>
		<td>
			<?php i12n_link( $user ); ?>
			<input type="file" name="i12n" id="i12n">
		</td>
	</tr>
</table>
