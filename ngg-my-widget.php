<?php
/*
Plugin Name: ngg My Widget
Plugin URI: https://github.com/enricator/nggMyWidget
Description: A first simple test trying to extend NGG.
Version: 0.0.1
Author: Enrico Imbalzano
Author URI: http://www.enricator.it
License: GPLv2 or later
*/
class nggMyWidget extends WP_Widget {
    function nggMyWidget() {
        parent::__construct( false, 'ngg My Widget' );
    }
    function widget( $args, $instance ) {
        extract($args);
        echo $before_widget;
        echo $before_title.$instance['title'].$after_title;
 
        //DA QUI INIZIA IL WIDGET VERO E PROPRIO
		?>
		<div class="foto_collegate">
		<!-- <h3 class="sidetitl">foto collegate</h3> -->
		<?php 		 
		$id_galleria = get_post_meta( get_the_ID(), 'gallery_id', true );
		// check if the custom field has a value
		if( ! empty( $id_galleria ) ) { 
			// slideshow
			$options = array(   'galleryid' => $id_galleria,
								'width'     => '250', 
								'height'    => '190' );
			$ngg_widget1 = new nggSlideshowWidget();
			$ngg_widget1->widget($args = array( 'widget_id'=> 'my_sidebar_1' ), $options);
			// empty arrays
			$options = array(); $args = array();
			// thumbnails
			$options = array('title'    => false, 
							 'items'    => 6,
							 'show'     => 'thumbnail' ,
							 'type'     => 'id',
							 'galleryid' => $id_galleria,
							 'width'    => '75', 
							 'height'   => '50', 
							 'exclude'  => 'all',
							 'list'     => '',
							 'webslice' => false );
							
			$ngg_widget2 = new nggMyNggWidget();
			$ngg_widget2->widget($args = array( 'widget_id'=> 'my_sidebar_2' ), $options);
		} else {
			echo "Nessuna immagine caricata";
		}
		?>
		  </div>
	    <?php
		
		//the_widget($widget, $instance, $args);
        //FINE WIDGET
 
        echo $after_widget;
    }
    function update( $new_instance, $old_instance ) {
        return $new_instance;
    }
    function form( $instance ) {
        $title = esc_attr($instance['title']); ?>
        <p><label for="<?php echo $this->get_field_id('title');?>">
        Titolo: <input class="widefat" id="<?php echo $this->get_field_id('title');?>" name="<?php echo $this->get_field_name('title');?>" type="text" value="<?php echo $title; ?>" />
        </label></p>
        <?php
    }
}
 
function my_register_widgets() {
    register_widget( 'nggMyWidget' );
}
 
add_action( 'widgets_init', 'my_register_widgets' );


class nggMyNggWidget extends nggWidget {

	function widget( $args, $instance ) {
		extract( $args );
        
        $title = apply_filters('widget_title', empty($instance['title']) ? '&nbsp;' : $instance['title'], $instance, $this->id_base);

		global $wpdb;
				
		$items 	= $instance['items'];
		$exclude = $instance['exclude'];
		$list = $instance['list'];
		$webslice = $instance['webslice'];
		$galleryid = $instance['galleryid'];

		$count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->nggpictures WHERE exclude != 1 ");
		if ($count < $instance['items']) 
			$instance['items'] = $count;

		$exclude_list = '';

		// THX to Kay Germer for the idea & addon code
		if ( (!empty($list)) && ($exclude != 'all') ) {
			$list = explode(',',$list);
			// Prepare for SQL
			$list = "'" . implode("', '", $list ) . "'";
			
			if ($exclude == 'denied')	
				$exclude_list = "AND NOT (t.gid IN ($list))";

			if ($exclude == 'allow')	
				$exclude_list = "AND t.gid IN ($list)";
            
            // Limit the output to the current author, can be used on author template pages
            if ($exclude == 'user_id' )
                $exclude_list = "AND t.author IN ($list)";                
		}
		
		if ( $instance['type'] == 'random' ) {
			$imageList = $wpdb->get_results("SELECT t.*, tt.* FROM $wpdb->nggallery AS t INNER JOIN $wpdb->nggpictures AS tt ON t.gid = tt.galleryid WHERE tt.exclude != 1 $exclude_list ORDER by rand() limit {$items}");
		} elseif ( $instance['type'] == 'id' ) {
			$imageList = $wpdb->get_results("SELECT t.*, tt.* FROM $wpdb->nggallery AS t INNER JOIN $wpdb->nggpictures AS tt ON t.gid = tt.galleryid WHERE tt.exclude != 1 AND tt.galleryid = $galleryid $exclude_list ORDER by rand() limit {$items}");
		} else {
			$imageList = $wpdb->get_results("SELECT t.*, tt.* FROM $wpdb->nggallery AS t INNER JOIN $wpdb->nggpictures AS tt ON t.gid = tt.galleryid WHERE tt.exclude != 1 $exclude_list ORDER by pid DESC limit 0,$items");
		}
		
        // IE8 webslice support if needed
		if ( $webslice ) {
			$before_widget .= "\n" . '<div class="hslice" id="ngg-webslice" >' . "\n";
            //the headline needs to have the class enty-title
            $before_title  = str_replace( 'class="' , 'class="entry-title ', $before_title);
			$after_widget  =  '</div>'."\n" . $after_widget;			
		}	
		                      
		echo $before_widget . $before_title . $title . $after_title;
		echo "\n" . '<div class="ngg-widget entry-content">'. "\n";
	
		if (is_array($imageList)){
			foreach($imageList as $image) {
				// get the URL constructor
				$image = new nggImage($image);

				// get the effect code
				$thumbcode = $image->get_thumbcode( $widget_id );
				
				// enable i18n support for alttext and description
				$alttext      =  htmlspecialchars( stripslashes( nggGallery::i18n($image->alttext, 'pic_' . $image->pid . '_alttext') ));
				$description  =  htmlspecialchars( stripslashes( nggGallery::i18n($image->description, 'pic_' . $image->pid . '_description') ));
				
				//TODO:For mixed portrait/landscape it's better to use only the height setting, if widht is 0 or vice versa
				$out = '<a href="' . $image->imageURL . '" title="' . $description . '" ' . $thumbcode .'>';
				// Typo fix for the next updates (happend until 1.0.2)
				$instance['show'] = ( $instance['show'] == 'orginal' ) ? 'original' : $instance['show'];
				
				if ( $instance['show'] == 'original' )
					$out .= '<img src="' . trailingslashit( home_url() ) . 'index.php?callback=image&amp;pid='.$image->pid.'&amp;width='.$instance['width'].'&amp;height='.$instance['height']. '" title="'.$alttext.'" alt="'.$alttext.'" />';
				else	
					$out .= '<img src="'.$image->thumbURL.'" width="'.$instance['width'].'" height="'.$instance['height'].'" title="'.$alttext.'" alt="'.$alttext.'" />';			
				
				echo $out . '</a>'."\n";
				
			}
		}
		
		echo '</div>'."\n";
		echo $after_widget;
		
	}
} // end class eiNggWidget

// register it
add_action('widgets_init', create_function('', 'return register_widget("nggMyNggWidget");'));


?>
