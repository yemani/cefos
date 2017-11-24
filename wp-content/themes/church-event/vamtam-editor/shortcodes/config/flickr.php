<?php
return array(
	'name' => __('Flickr', 'church-event') ,
	'desc' => __('This element is usefull if you have a Flickr account. Use <a href="http://idgettr.com/" target="_blank">idGettr</a> if you don\'t know your ID.<br/><br/>.' , 'church-event'),
	'icon' => array(
		'char' => WPV_Editor::get_icon('flickr'),
		'size' => '30px',
		'lheight' => '45px',
		'family' => 'vamtam-editor-icomoon',
	),
	'value' => 'flickr',
	'controls' => 'size name clone edit delete',
	'options' => array(

		array(
			'name' => __('Flickr ID', 'church-event'),
			'desc' => __('Use <a href="http://idgettr.com/" target="_blank">idGettr</a> if you don\'t know your ID.<br/><br/>', 'church-event'),
			'id' => 'id',
			'default' => '',
			'type' => 'text'
		),

		array(
			'name' => __('Type', 'church-event'),
			'id' => 'type',
			'default' => 'page',
			'options' => array(
				'user' => __('User', 'church-event'),
				'group' => __('Group', 'church-event'),
			),
			'type' => 'select',
		),

		array(
			'name' => __('Count', 'church-event'),
			'desc' => '',
			'id' => 'count',
			'default' => 4,
			'min' => 0,
			'max' => 10,
			'type' => 'range'
		),
		array(
			'name' => __('Display', 'church-event'),
			'id' => 'display',
			'default' => 'latest',
			'options' => array(
				'latest' => __('Latest', 'church-event'),
				'random' => __('Random', 'church-event'),
			),
			'type' => 'select',
		),

		array(
			'name' => __('Title (optional)', 'church-event') ,
			'desc' => __('The title is placed just above the element.', 'church-event'),
			'id' => 'column_title',
			'default' => '',
			'type' => 'text'
		) ,
		array(
			'name' => __('Title Type (optional)', 'church-event') ,
			'id' => 'column_title_type',
			'default' => 'single',
			'type' => 'select',
			'options' => array(
				'single' => __('Title with divider next to it', 'church-event'),
				'double' => __('Title with divider below', 'church-event'),
				'no-divider' => __('No Divider', 'church-event'),
			),
		) ,


	) ,
);
