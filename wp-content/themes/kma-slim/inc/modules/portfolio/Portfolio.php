<?php
/**
 * Portfolio
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Portfolio {

    /**
     * Portfolio constructor.
     */
    function __construct() {

    }

    /**
     * @return null
     */
    public function createPostType() {

        $work = new Custom_Post_Type( 'Work', array(
            'supports'           => array( 'title', 'revisions' ),
            'menu_icon'          => 'dashicons-images-alt2',
            'rewrite'            => array( 'with_front' => false ),
            'has_archive'        => false,
            'menu_position'      => null,
            'public'             => false,
            'publicly_queryable' => false,
        ) );

        $work->add_taxonomy( 'Artist' );

	    $work->add_meta_box( 'Work Details', array(
		    'Photo File'           => 'image',
		    'Feature on Home page' => 'boolean',
	    ) );

        $work->add_meta_box(
            'Long Description',
            array(
                'HTML' => 'wysiwyg',
            )
        );

	    // replace checkboxes for the format taxonomy with radio buttons and a custom meta box
	    function layout_term_radio_checklist( $args ) {
		    if ( ! empty( $args['taxonomy'] ) && $args['taxonomy'] === 'artist' ) {
			    if ( empty( $args['walker'] ) || is_a( $args['walker'], 'Walker' ) ) { // Don't override 3rd party walkers.
				    if ( ! class_exists( 'Layout_Walker_Category_Radio_Checklist' ) ) {
					    include(wp_normalize_path(get_template_directory().'/inc/Layout_Walker.php'));
				    }
				    $args['walker'] = new Layout_Walker_Category_Radio_Checklist;
			    }
		    }
		    return $args;
	    }
	    add_filter( 'wp_terms_checklist_args', 'layout_term_radio_checklist' );

    }

	/**
	 * @return null
	 */
	public function createAdminColumns() {

        //Create column labels in admin view
        add_filter('manage_work_posts_columns', 'columns_head_work', 0);
        function columns_head_work($defaults) {

            $defaults = array(
                'title'      => 'Title',
                'artist'     => 'Artist',
                'featured'   => 'Featured',
                'work_photo' => 'Photo',
                'date'       => 'Date'
            );

            return $defaults;
        }

        //Creates data used in each column
        add_action('manage_work_posts_custom_column', 'columns_content_work', 0, 2);
        function columns_content_work($column_name, $post_ID) {
            switch ( $column_name ) {
                case 'lead_type':
                    $term = wp_get_object_terms( $post_ID, 'type' );
                    echo (isset($term[0]->name) ? $term[0]->name : null );
                    break;

                case 'work_photo':
                    $photo = get_post_meta( $post_ID, 'work_details_photo_file', TRUE );
                    echo (isset($photo) ? '<img src ="'.$photo.'" class="img-fluid" style="width:400px; max-width:100%;" >' : null );
                    break;

                case 'featured':
                    $featured = get_post_meta( $post_ID, 'work_details_feature_on_home_page', true );
                    echo ( $featured == 'on' ? 'TRUE' : 'FALSE' );
                    break;
            }
        }

        //Adds a dropdown in the admin view to filter by artist (taxonomy)
        add_action( 'restrict_manage_posts', 'admin_posts_filter_restrict_manage_posts' );
        function admin_posts_filter_restrict_manage_posts(){
            $type = 'post';
            if (isset($_GET['post_type'])) {
                $type = $_GET['post_type'];
            }

            if ('work' == $type){

                $values = get_terms( array(
                    'taxonomy' => 'artist',
                    'hide_empty' => false,
                ) );

                ?>
                <select name="artist">
                    <option value="">All Artists</option>
                    <?php
                    $current_v = isset($_GET['artist'])? $_GET['artist']:'';
                    foreach ($values as $label => $value) {
                        printf
                        (
                            '<option value="%s"%s>%s</option>',
                            $value->slug,
                            $value->slug == $current_v ? ' selected="selected"':'',
                            $value->name
                        );
                    }
                    ?>
                </select>
                <?php
            }
        }

	}

    /**
     * @param author ( post type category )
     * @return HTML
     */
    public function getWork( $taxonomy = '', $requestArray = array() ){

	    $request = array(
		    'posts_per_page' => - 1,
		    'offset'         => 0,
		    'order'          => 'ASC',
		    'orderby'        => 'menu_order',
		    'post_type'      => 'work',
		    'post_status'    => 'publish',
	    );

	    if ( $taxonomy != '' ) {
		    $categoryarray        = array(
			    array(
				    'taxonomy'         => 'artist',
				    'field'            => 'slug',
				    'terms'            => $taxonomy,
				    'include_children' => false,
			    ),
		    );
		    $request['tax_query'] = $categoryarray;
	    }

	    $args = array_merge( $request, $requestArray );

        //echo '<pre>',print_r($args),'</pre>';

	    $results = get_posts( $args );

        $resultArray = array();
        foreach ( $results as $item ){

        	$taxonomies = get_the_terms($item, artist);

	        array_push( $resultArray, array(
		        'id'          => ( isset( $item->ID ) ? $item->ID : null ),
		        'name'        => ( isset( $item->post_title ) ? $item->post_title : null ),
		        'slug'        => ( isset( $item->post_name ) ? $item->post_name : null ),
		        'photo'       => ( isset( $item->work_details_photo_file ) ? $item->work_details_photo_file : null ),
		        'author'      => $taxonomies[0]->name,
		        'featured'    => ( isset( $item->work_details_feature_on_home_page ) ? $item->work_details_feature_on_home_page : null ),
		        'description' => ( isset( $item->long_description_html ) ? $item->long_description_html : null ),
		        'link'        => get_term_link( $taxonomies[0] ),
	        ) );

        }

        return $resultArray;

    }

    /**
     * @param $taxonomy
     * @param array for get_posts
     * @return HTML
     */
    public function getWorkSlider($taxonomy = '', $requestArray = array()){

	    $resultArray = $this->getWork($taxonomy, $requestArray);
        $output = '';

        $i = 1;
        foreach($resultArray as $item){
	        $output .= '<portfolioslide :id="'.$i.'" image="'.$item['photo'].'" artist="'.$item['author'].'" title="'.$item['name'].'" link="'.$item['link'].'" :islast="'.($i==count($resultArray) ? 'true' : 'false' ).'" ></portfolioslide>';
            $i++;
        }

        return $output;

    }

    public function getArtists(){

        return get_terms('artist');

    }

    public function addTaxonomyMeta(){

        // SANITIZE DATA
        function ___sanitize_artist_meta_text ( $value ) {
            return sanitize_text_field ($value);
        }

        // GETTER (will be sanitized)
        function ___get_artist_video_embed( $term_id ) {
            $value = get_term_meta( $term_id, '__artist_video_embed', true );
            $value = ___sanitize_artist_meta_text( $value );
            return $value;
        }

        // ADD FIELD TO CATEGORY TERM PAGE
        add_action( 'artist_add_form_fields', '___add_form_field_artist_video_embed' );
        function ___add_form_field_artist_video_embed() { ?>
            <?php wp_nonce_field( basename( __FILE__ ), 'artist_video_embed_nonce' ); ?>
            <div class="form-field artist-meta-text-wrap">
                <label for="artist-meta-video-embed"><?php _e( 'Video Embed Code', 'kmaslim' ); ?></label>
                <textarea style="min-height:200px;" name="artist_video_embed" id="artist-video-embed" class="artist-video-embed-field"></textarea>
            </div>
        <?php }

        // ADD FIELD TO CATEGORY EDIT PAGE
        add_action( 'artist_edit_form_fields', '___edit_form_field_artist_video_embed' );
        function ___edit_form_field_artist_video_embed( $term ) {
            $value  = ___get_artist_video_embed( $term->term_id );
            if ( ! $value )
                $value = ""; ?>

            <tr class="form-field artist-video-embed-wrap">
                <th scope="row"><label for="artist-video-embed"><?php _e( 'Video Embed Code', 'text_domain' ); ?></label></th>
                <td>
                    <?php wp_nonce_field( basename( __FILE__ ), 'artist_video_embed_nonce' ); ?>
                    <textarea style="min-height:200px;" name="artist_video_embed" id="artist-video-embed" class="artist-video-embed-field"><?php echo esc_attr( $value ); ?></textarea>
                </td>
            </tr>
        <?php }

        // SAVE TERM META (on term edit & create)
        add_action( 'edit_artist',   '___save_artist_video_embed' );
        add_action( 'create_artist', '___save_artist_video_embed' );
        function ___save_artist_meta_text( $term_id ) {
            // verify the nonce --- remove if you don't care
            if ( ! isset( $_POST['artist_video_embed_nonce'] ) || ! wp_verify_nonce( $_POST['artist_video_embed_nonce'], basename( __FILE__ ) ) )
                return;
            $old_value  = ___get_artist_video_embed( $term_id );
            $new_value = isset( $_POST['artist_video_embed'] ) ? ___sanitize_artist_meta_text ( $_POST['artist_video_embed'] ) : '';
            if ( $old_value && '' === $new_value )
                delete_term_meta( $term_id, '__artist_video_embed' );
            else if ( $old_value !== $new_value )
                update_term_meta( $term_id, '__artist_video_embed', $new_value );
        }

    }

}