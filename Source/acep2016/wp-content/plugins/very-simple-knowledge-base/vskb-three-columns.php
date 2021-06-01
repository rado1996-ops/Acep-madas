<?php
// Creating the shortcode for three columns
function vskb_three_columns( $vskb_atts ) {
	$vskb_atts = shortcode_atts( array( 
		'include' => '', // include certain categories
		'exclude' => '', // exclude certain categories
		'hide_empty' => 1, // 1 means do not list empty categories
		'posts_per_page' => -1, // -1 means list all posts
		'order' => 'desc', // list posts in descending order
		'orderby' => 'date' // order posts by date
	), $vskb_atts);

	$return = "";

	$return .= '<div id="vskb-three">';

	$vskb_cat_args = array(
		'include' => $vskb_atts['include'],
		'exclude' => $vskb_atts['exclude'],
		'hide_empty' => $vskb_atts['hide_empty']
	);

	$vskb_cats = get_categories( $vskb_cat_args );

	foreach ($vskb_cats as $cat) :

		$return .= '<ul class="vskb-cat-list"><li class="vskb-cat-name"><a href="'. get_category_link( $cat->cat_ID ) .'" title="'. $cat->name .'" >'. $cat->name .'</a></li>';

		$vskb_post_args = array(
			'posts_per_page' => $vskb_atts['posts_per_page'],
			'order' => $vskb_atts['order'],
			'orderby' => $vskb_atts['orderby'],
			'category__in' => $cat->cat_ID // list posts from all categories and posts from sub category will be hidden from their parent category
		);

		$vskb_posts = get_posts( $vskb_post_args ); 

		foreach( $vskb_posts AS $single_post ) :
			$return .=  '<li class="vskb-post-name">';
			$return .=  '<a href="'. get_permalink( $single_post->ID ) .'" rel="bookmark" title="'. get_the_title( $single_post->ID ) .'">'. get_the_title( $single_post->ID ) .'</a>';
			$return .=  '</li>';
		endforeach;
		
		$return .=  '</ul>';
		
	endforeach;
	
	$return .= '</div>';

	return $return;
	
}
add_shortcode('knowledgebase-three', 'vskb_three_columns');

?>