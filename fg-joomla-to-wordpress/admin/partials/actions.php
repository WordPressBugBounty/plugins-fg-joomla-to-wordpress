				<tr>
					<th scope="row">&nbsp;</th>
					<td>
						<div class="submit_button_with_spinner">
							<?php submit_button( __('Save settings', 'fg-joomla-to-wordpress'), 'secondary', 'save', false ); ?>
							<span id="save_spinner" class="spinner"></span>
						</div>
						<div id="save_message" class="action_message"></div>
						
						<div class="submit_button_with_spinner">
							<?php submit_button( __('Start / Resume the import', 'fg-joomla-to-wordpress'), 'primary', 'import', false ); ?>
							<span id="import_spinner" class="spinner"></span>
						</div>
						<div id="action_message" class="action_message"></div>
						<?php submit_button( __('Stop import', 'fg-joomla-to-wordpress'), 'secondary', 'stop-import' ); ?>
					</td>
				</tr>
