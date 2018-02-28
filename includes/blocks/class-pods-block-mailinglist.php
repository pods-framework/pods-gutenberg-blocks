<?php

class Pods_Block_MailingList extends Pods_Block {

	/**
	 * Indexes for each block ID.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 * @var    array
	 */
	public $block_index = array();

	/**
	 * Form objects generated by this block.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 * @var    array $form_objects Form objects generated by this block.
	 */
	public $form_objects = array();

	/**
	 * Form IDs that were already processed by this block.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 * @var    array $processed_forms Form IDs that were already processed by this block.
	 */
	public $processed_forms = array();





	// # BLOCK RENDER --------------------------------------------------------------------------------------------------

	/**
	 * Display block contents on frontend.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @uses   Pods_Block::can_view_block()
	 * @uses   Pods_Block_MailingList::get_form_object()
	 * @uses   PodsCommon::get_base_path()
	 * @uses   PodsCommon::get_browser_class()
	 * @uses   PodsFormDisplay::enqueue_form_scripts()
	 * @uses   PodsFormDisplay::get_field()
	 * @uses   PodsFormDisplay::pods_footer()
	 * @uses   PodsFormsModel::get_field_value()
	 *
	 * @return string|null
	 */
	public function render_block( $attributes = array() ) {

		// If we are in the WordPress admin, do not render block.
		if ( is_admin() ) {
			return null;
		}

		// Get block logic.
		$logic = isset( $attributes['conditionalLogic'] ) ? $attributes['conditionalLogic'] : array();

		// If conditional logic did not pass, return.
		if ( ! $this->can_view_block( $logic ) ) {
			return null;
		}

		// Require form display class.
		if ( ! class_exists( 'PodsFormDisplay' ) ) {
			require_once PodsCommon::get_base_path() . '/form_display.php';
		}

		// Get form object.
		$form = $this->get_form_object( $attributes );

		// Handle form submission.
		if ( $form['id'] === rgpost( 'pods_submit' ) ) {

			// Get field values.
			$field_values = PodsForms::post( 'pods_field_values' );

			// Validate.
			$is_valid = PodsFormDisplay::validate( $form, $field_values );

			// If form is valid, process feed.
			if ( $is_valid && ! $this->was_form_processed( $form ) ) {

				// Get entry object.
				$entry = PodsFormsModel::create_lead( $form );

				// Prepare feed object.
				$feed = $this->get_feed_object( $attributes, $form );

				// Process feed.
				$this->process_feed( $feed, $entry, $form );

				// Mark form as processed.
				$this->form_processed( $form );

				// Get confirmation.
				$confirmation = array_values( $form['confirmations'] );
				$confirmation = $confirmation[0];

				// Display confirmation message.
				return PodsFormDisplay::get_confirmation_message( $confirmation, $form, $entry );

			}

		}

		// Enqueue form scripts.
		PodsFormDisplay::enqueue_form_scripts( $form );

		// Open form wrapper.
		$html = sprintf( "<div class='%s pods_wrapper %s' id='pods_wrapper_%s'>", PodsCommon::get_browser_class(), ( $form['cssClass'] ? $form['cssClass'] . '_wrapper' : '' ), $form['id'] );
		$html .= sprintf( "<form method='post' id='pods_%s' class='%s'>", $form['id'], $form['cssClass'] );

		// Display form heading.
		if ( rgar( $attributes, 'formTitle' ) || rgar( $attributes, 'formDescription' ) ) {

			$html .= "<div class='pods_heading'>";
			$html .= rgar( $attributes, 'formTitle' ) ? sprintf( "<h3 class='pods_title'>%s</h3>", $form['title'] ) : '';
			$html .= rgar( $attributes, 'formDescription' ) ? sprintf( "<span class='pods_description'>%s</span>", $form['description'] ) : '';
			$html .= '</div>';

		}

		// Begin form body.
		$html .= "<div class='pods_body'>";
		$html .= "<ul class='pods_fields top_label form_sublabel_below'>";

		// Display fields.
		foreach ( $form['fields'] as $field ) {

			// Get field value.
			$field_value = PodsFormsModel::get_field_value( $field );

			$html .= PodsFormDisplay::get_field( $field, $field_value );

		}

		// Display form footer.
		$html .= '</ul></div>';
		$html .= PodsFormDisplay::pods_footer( $form, 'pods_footer top_label', false, array(), '', false, false, 0 );
		$html .= sprintf( "<input type='hidden' class='pods_hidden' name='pods_submit' value='%s' />", esc_attr( $form['id'] ) );
		$html .= sprintf( "<input type='hidden' class='pods_hidden' name='is_submit_%s' value='1' />", esc_attr( $form['id'] ) );
		$html .= sprintf( "<input type='hidden' class='pods_hidden' name='block_index' value='%d' />", esc_attr( $block_index ) );

		// Close form wrapper.
		$html .= '</form></div>';

		return $html;

	}

	/**
	 * Get form object for block.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @uses   PodsFormsModel::convert_field_objects()
	 *
	 * @return array
	 */
	public function get_form_object( $attributes = array() ) {

		// Get block index.
		if ( isset( $this->block_index[ $attributes['blockID'] ] ) ) {
			$block_index                                 = $this->block_index[ $attributes['blockID'] ] + 1;
			$this->block_index[ $attributes['blockID'] ] = $block_index;
		} else {
			$block_index                                 = 0;
			$this->block_index[ $attributes['blockID'] ] = $block_index;
		}

		// Append block index to block ID.
		$attributes['blockID'] .= '-' . $block_index;

		// Get existing form object.
		if ( rgar( $this->form_objects, $attributes['blockID'] ) ) {
			return $this->form_objects[ $attributes['blockID'] ];
		}

		// Get form orientation and confirmation ID.
		$orientation     = rgar( $attributes, 'orientation' ) ? $attributes['orientation'] : 'vertical';
		$confirmation_id = uniqid();

		// Initialize form object.
		$form = array(
			'id'            => esc_attr( $attributes['blockID'] ),
			'title'         => rgar( $attributes, 'formTitle' ) ? esc_html( $attributes['formTitle'] ) : esc_html__( 'Newsletter Signup', 'pods-gutenberg-blocks' ),
			'description'   => wp_kses_post( rgar( $attributes, 'formDescription' ) ),
			'cssClass'      => 'horizontal' === $orientation ? 'pods_simple_horizontal' : '',
			'fields'        => array(),
			'button'        => array(
				'type' => 'text',
				'text' => rgar( $attributes, 'submitText' ) ? esc_html( $attributes['submitText'] ) : esc_html__( 'Submit', 'pods-gutenberg-blocks' ),
			),
			'confirmations' => array(
				$confirmation_id => array(
					'id'        => $confirmation_id,
					'name'      => 'Default Confirmation',
					'isDefault' => true,
					'type'      => 'message',
					'message'   => rgar( $attributes, 'confirmationText' ) ? wp_kses_post( $attributes['confirmationText'] ) : esc_html__( 'Thank you for subscribing to our newsletter!', 'pods-gutenberg-blocks' ),
				),
			),
		);

		// Add Name field.
		$form['fields'][] = array(
			'id'           => 1,
			'type'         => 'name',
			'label'        => esc_html__( 'Name', 'pods-gutenberg-blocks' ),
			'isRequired'   => false,
			'nameFormat'   => 'advanced',
			'formId'       => esc_html( $attributes['blockID'] ),
			'description'  => '',
			'defaultValue' => '',
			'pageNumber'   => 1,
			'visibility'   => false === rgar( $attributes, 'nameField' ) ? 'hidden' : 'visible',
			'inputs'       => array(
				array(
					'id'       => '1.2',
					'label'    => esc_html__( 'Prefix', 'pods-gutenberg-blocks' ),
					'name'     => '',
					'isHidden' => true,
					'choices'  => array(
						array(
							'text'       => 'Mr.',
							'value'      => 'Mr.',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Mrs.',
							'value'      => 'Mrs.',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Miss',
							'value'      => 'Miss',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Ms.',
							'value'      => 'Ms.',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Dr.',
							'value'      => 'Dr.',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Prof.',
							'value'      => 'Prof.',
							'isSelected' => false,
							'price'      => '',
						),
						array(
							'text'       => 'Rev.',
							'value'      => 'Rev.',
							'isSelected' => false,
							'price'      => '',
						),
					),
				),
				array(
					'id'          => '1.3',
					'label'       => esc_html__( 'First', 'pods-gutenberg-blocks' ),
					'name'        => '',
					'placeholder' => 'horizontal' === $orientation ? esc_html__( 'First Name', 'pods-gutenberg-blocks' ) : '',
				),
				array(
					'id'       => '1.4',
					'label'    => esc_html__( 'Middle', 'pods-gutenberg-blocks' ),
					'name'     => '',
					'isHidden' => true,
				),
				array(
					'id'          => '1.6',
					'label'       => esc_html__( 'Last', 'pods-gutenberg-blocks' ),
					'name'        => '',
					'placeholder' => 'horizontal' === $orientation ? esc_html__( 'Last Name', 'pods-gutenberg-blocks' ) : '',
				),
				array(
					'id'       => '1.8',
					'label'    => esc_html__( 'Suffix', 'pods-gutenberg-blocks' ),
					'name'     => '',
					'isHidden' => true,
				),
			),
		);

		// Add Email field.
		$form['fields'][] = array(
			'id'                  => 2,
			'type'                => 'email',
			'label'               => esc_html__( 'Email', 'pods-gutenberg-blocks' ),
			'placeholder'         => 'horizontal' === $orientation ? esc_html__( 'Email Address', 'pods-gutenberg-blocks' ) : '',
			'isRequired'          => true,
			'noDuplicates'        => false,
			'formId'              => esc_html( $attributes['blockID'] ),
			'description'         => '',
			'defaultValue'        => '',
			'emailConfirmEnabled' => '',
			'pageNumber'          => 1,
		);

		// Convert field objects.
		$form = PodsFormsModel::convert_field_objects( $form );

		// Save form object to class.
		$this->form_objects[ $attributes['blockID'] ] = $form;

		return $form;

	}

	/**
	 * Get feed object for subscribing user.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $attributes Block attributes.
	 * @param array $form       Form object.
	 *
	 * @return array
	 */
	public function get_feed_object( $attributes = array(), $form = array() ) {

		return array();

	}

	/**
	 * Dispatch feed object to feed processor.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $feed  Feed object.
	 * @param array $entry Entry object.
	 * @param array $form  Form object.
	 *
	 * @return array
	 */
	public function process_feed( $feed = array(), $entry = array(), $form = array() ) {

		return $entry;

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Mark form as processed.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $form Form object.
	 */
	public function form_processed( $form = array() ) {

		if ( ! $this->was_form_processed( $form ) ) {
			$this->processed_forms[] = $form['id'];
		}

	}

	/**
	 * Determine if form was already processed.
	 *
	 * @since  1.0-beta-3
	 * @access public
	 *
	 * @param array $form Form object.
	 *
	 * @return bool
	 */
	public function was_form_processed( $form = array() ) {

		return in_array( $form['id'], $this->processed_forms );

	}

}
