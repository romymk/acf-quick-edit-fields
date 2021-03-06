<?php

namespace ACFQuickEdit\Fields;

if ( ! defined( 'ABSPATH' ) )
	die('Nope.');

class RadioField extends ChoiceField {

	/**
	 *	@inheritdoc
	 */
	public function render_input( $input_atts, $is_quickedit = true ) {
		
		$output = '';
		$output .= sprintf( '<ul class="acf-radio-list%s" data-acf-field-key="%s">', 
			$this->acf_field['other_choice'] ? ' other' : '',
			$this->acf_field['key'] 
		);

		foreach($this->acf_field['choices'] as $name => $value) {

			$id = $this->core->prefix( $this->acf_field['key'] . '-' . $name );

			$output .= sprintf( '<li><label for="%s">', $id );
			$output .= sprintf( '<input %s />%s', acf_esc_attr( array(
				'id'					=> $id,
				'type'					=> 'radio',
				'value'					=> $name,
				'class'					=> 'acf-quick-edit',
				'data-acf-field-key'	=> $this->acf_field['key'],
				'name'					=> $input_atts['name'],
			) ), $value );


			$output .= '</label></li>';

		}

		if ( $this->acf_field['other_choice'] ) {

			$id = $this->core->prefix( $this->acf_field['key'] . '-other' );

			$output .= sprintf( '<li><label for="%s">', $id );
			$output .= sprintf( '<input %s />', acf_esc_attr( array(
				'id'					=> $id,
				'type'					=> 'radio',
				'value'					=> 'other',
				'class'					=> 'acf-quick-edit',
				'data-acf-field-key'	=> $this->acf_field['key'],
				'name'					=> $input_atts['name'],
			) ) );
			$output .= sprintf( '<input %s />', acf_esc_attr( array(
				'type'					=> 'text',
				'class'					=> 'acf-quick-edit',
				'data-acf-field-key'	=> $this->acf_field['key'],
				'name'					=> $input_atts['name'],
				'style'					=> 'width:initial',
				'disabled'				=> 'disabled',
			) ) );

			$output .= '</label></li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 *	@inheritdoc
	 */
	public function update( $post_id, $is_quickedit = true ) {
		$param_name = $this->acf_field['key'];

		if ( isset ( $_REQUEST['acf'][ $param_name ] ) ) {
			$value = $_REQUEST['acf'][ $param_name ];
		} else {
			$value = null;
		} 

		if ( in_array( $this->dont_change_value, (array) $value ) ) {
			return;
		}


		update_field( $this->acf_field['name'], $value, $post_id );

	}


}