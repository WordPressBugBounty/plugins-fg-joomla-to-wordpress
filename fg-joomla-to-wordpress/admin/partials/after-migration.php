		<table class="form-table">
			<tr>
				<th scope="row" colspan="2"><h3><?php _e('After the migration', 'fg-joomla-to-wordpress'); ?></h3></th>
			</tr>
			<tr>
				<th scope="row"><?php _e('If you have links between articles, you need to modify internal links.', 'fg-joomla-to-wordpress'); ?></th>
				<td>
					<form id="form_modify_links" method="post">
						<?php wp_nonce_field( 'modify_links', 'fgj2wp_nonce_modify_links' ); ?>
						<div class="submit_button_with_spinner">
							<?php submit_button( __('Modify internal links', 'fg-joomla-to-wordpress'), 'primary', 'modify_links', false ); ?>
							<span id="modify_links_spinner" class="spinner"></span>
						</div>
						<div id="modify_links_message" class="action_message"></div>
					</form>
				</td>
			</tr>
		</table>
