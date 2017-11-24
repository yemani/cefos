<?php
return array(
	"name" => "Lightbox",
	"value" => "lightbox",
	"options" => array(
		array(
			"name" => __("Trigger Source", 'church-event') ,
			'desc' => __('You can put any HTML in here, except for links. Try putting some text or an image. When someone click on the text/image the lightbox will open.', 'church-event'),
			"id" => "content",
			"default" => '<img src="http://makalu.vamtam.com/wp-content/uploads/2013/03/service-icon-2.png" alt="service-icon-2" width="221" height="143" class="alignleft size-full wp-image-180" />',
			"type" => "textarea"
		) ,
		array(
			"name" => __("Lightbox Source", 'church-event') ,
			'desc' => __('Put here a link to what is to be displayed in the lightbox.', 'church-event'),
			"id" => "href",
			"size" => 30,
			"default" => "http://makalu.vamtam.com/wp-content/uploads/2013/03/service-icon-3.png",
			"type" => "text",
		) ,
		
		array(
			"name" => __("Group (optional)", 'church-event') ,
			"desc" => __("If two or more lightboxes have the same name and they are shown on the same page, the lightbox will have navigation arrows and you can change the images without closing the lightbox.", 'church-event') ,
			"id" => "group",
			"default" => '',
			"type" => "text"
		) ,
		array(
			"name" => __("Force Iframe", 'church-event') ,
			"desc" => __("If your source is a embeddable video or a site, you will have to enable this option." , 'church-event') ,
			"id" => "iframe",
			"default" => '',
			"type" => "toggle"
		) ,
	) ,
);
