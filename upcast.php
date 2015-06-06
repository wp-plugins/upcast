<?php
/**
 * @package UpCast
 * @author JagTech
 * @version 1.0.0
 */
/*
Plugin Name: UpCast
Plugin URI: https://upcast.me/plugins/wp/upcast.zip
Description: Display customised podcast lists and episodes from upcast.me.
Author: JagTech
Version: 1.0.0
Author URI: http://jagtech.biz/
*/

/*  Copyright 2015  UpCast  (email : support@upcast.me)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once('settings.php');
include_once('widget.php');

function upcast_image($atts, $content = '') {
	return upcast_feed($atts, $content, 'images');
}

function upcast_thumbnail($atts, $content = '') {
	return upcast_feed($atts, $content, 'thumbs');
}

function upcast_rss($atts, $content = '') {
	return upcast_feed($atts, $content);
}

function upcast_feed($atts, $content = '', $type = NULL) {
	
	$options = get_option('upcast_options');	
	
	extract(shortcode_atts(Array(
		'feed' => isset($options['rss_link']) ? $options['rss_link'] : false,
		'template' => isset($options['template_rss']) ? $options['template_rss'] : '',		
		'source' => isset($options['analytics_source']) ? $options['analytics_source'] : false
	), $atts));
	
	if (!$feed)
		return;

	//error_log('feed1='.$feed);
	$feed = modify_upcast_url($feed, NULL, NULL, NULL, $source, $type);
	//error_log('feed2='.$feed);
			
	$text = $content;
	
	if ($text == '')
		$text = $template;
		
	if ($type !== NULL) {	
		$text = '<img src="'.$feed.'">';
		return $text;
	} else {
		
		$rss = new SimplePie();
		$rss->set_feed_url($feed);
		$rss->set_cache_class('WP_Feed_Cache');
		$rss->set_file_class('WP_SimplePie_File');
		if (isset($_GET['sp']) && $_GET['sp']=='flush')
			$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 0));
		else
			$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 43200));
		$rss->init();
		$rss->handle_content_type();
		
		if ($text != '') {		
			$text = preg_replace('/\[image_title\]/',$rss->get_image_title(),$text);
			$text = preg_replace('/\[image_link\]/',$rss->get_image_link(),$text);
			$text = preg_replace('/\[image_url\]/',$rss->get_image_url(),$text);
			$text = preg_replace('/\[image_width\]/',$rss->get_image_width(),$text);
			$text = preg_replace('/\[image_height\]/',$rss->get_image_height(),$text);
			$text = preg_replace('/\[title\]/',$rss->get_title(),$text);
			if ($rss_category = $rss->get_category())
				$text = preg_replace('/\[category\]/',$rss_category->get_label(),$text);
			if ($rss_author = $rss->get_author())
				$text = preg_replace('/\[author\]/',$rss_author->get_name(),$text);
			$text = preg_replace('/\[link\]/',$rss->get_link(),$text);
			$text = preg_replace('/\[description\]/',$rss->get_description(),$text);
			$text = preg_replace('/\[copyright\]/',$rss->get_copyright(),$text);
			return do_shortcode($text);	
		} else {
			$image_width = $rss->get_image_width();
			$image_height = $rss->get_image_height();
			$image_link = $rss->get_image_link();
			$image_url = $rss->get_image_url();
			$image_title = $rss->get_image_title();
			if ($image_url) {
				if ($image_link)
					$text = '<a href="'.$image_link.'">';
				$text .= '<img src="'.$image_url.'" '.
						($image_title ? 'title="'.$image_title.'" ' : '').
						($image_width ? 'width="'.$image_width.'" ' : '').
						($image_height ? 'height="'.$image_height.'" ' : '').
						'>';
				if ($image_link)
					$text .= '</a>';
			}
			return $text;		
		}
		
	}
}

function upcast_shortcode ($atts, $content = null) {
	//define('WP_DEBUG', true);
	$options = get_option('upcast_options');
	$system_date_format = 'M j, Y'; //get_option('date_format', 'M j, Y');
	$default_columns = '';
	$default_column_names = '';
	foreach (explode(',', UPCAST_FIELD_NAMES) as $field) {
		if (isset($options['column_'.$field]) && $options['column_'.$field] == 'on') {
			if ($default_columns != '') {
				$default_columns .= ',';
				$default_column_names .= ',';
			}
			$default_columns .= $field;
			$default_column_names .= (isset($options['header_'.$field]) && $options['header_'.$field]) ? $options['header_'.$field] : ucwords($field);
		}
	}
	if ($default_columns == '') {
		$default_columns = UPCAST_DEFAULT_COLUMNS;
		$default_column_names = implode(',', array_map('ucwords', explode(',', UPCAST_DEFAULT_COLUMNS)));
	}
		
	extract(shortcode_atts(Array(
		'feed' => isset($options['rss_link']) ? $options['rss_link'] : false,
		'max' => isset($options['rss_max']) ? $options['rss_max'] : false,
		'future' => isset($options['rss_future']) ? ($options['rss_future'] == 'on') : false,
		'skip' => isset($options['rss_skip_items']) ? $options['rss_skip_items'] : 0,
		'offset' => isset($options['rss_skip_days']) ? $options['rss_skip_days'] : 0,
		'files' => isset($options['rss_files_only']) ? ($options['rss_files_only'] == 'on') : false,
		'source' => isset($options['analytics_source']) ? $options['analytics_source'] : 'wordpress',
		'columns' => $default_columns,
		'header' => isset($options['rss_header']) ? ($options['rss_header'] == 'on') : true,
		'headers' => $default_column_names,
		'table' => true,
		'template' => isset($options['template_row']) ? $options['template_row'] : '',
		'title' => isset($options['filter_title']) ? $options['filter_title'] : false,
		'description' => isset($options['filter_desc']) ? $options['filter_desc'] : false,
		'pubdate' => isset($options['filter_pubdate']) ? $options['filter_pubdate'] : false,
		'date' => isset($options['filter_date']) ? $options['filter_date'] : false,
		'category' => isset($options['filter_category']) ? $options['filter_category'] : false,
		'subject' => isset($options['filter_subject']) ? $options['filter_subject'] : false,
		'author' => isset($options['filter_author']) ? $options['filter_author'] : false,
		'format' => false,
		'dates' => isset($options['rss_date_format']) && $options['rss_date_format'] ? 
						$options['rss_date_format'] : $system_date_format,
		'zone' => (isset($options['rss_time_zone']) && $options['rss_time_zone']) ? 
						$options['rss_time_zone'] : (get_option('timezone_string', false) ? 
													 get_option('timezone_string') :  
													 date_default_timezone_get()),
		'sort' => 'default'
	), $atts));


	$old_tz = date_default_timezone_get();
	date_default_timezone_set($zone);
	
	if ($sort == 'default') {
		$sort = ($future ? '+' : '-') . 'pubdate';
	}
	
	if ($headers == $default_column_names && $columns != $default_columns) {
		$headers = implode(',', array_map('ucwords', explode(',', $columns)));
	}
	$display_columns = explode(',',$columns);
	$display_column_headers = explode(',',$headers);
	
	# If no feed, return with nothing
	if (!$feed)
		return;
		
	//error_log('feed1='.$feed);
	$feed = modify_upcast_url($feed, $max, $future, $files, $source);
	//error_log('feed2='.$feed);
	# Create loop content
	$rss = new SimplePie_upcast();
	$rss->set_feed_url($feed);
	$rss->set_cache_class('WP_Feed_Cache');
	$rss->set_file_class('WP_SimplePie_File');
	if (isset($_GET['sp']) && $_GET['sp']=='flush')
		$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 0));
	else
		$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 43200));
	$rss->init();
	$rss->handle_content_type();
	$rss->set_sortorder($sort);
	
	if (($rss_author = $rss->get_author()) != FALSE)
		$rss_author_name = $rss_author->get_name();
	else
		$rss_author_name = '';
	
	$maxitems = $rss->get_item_quantity();
	
	$output = '';

	$rss_items = $rss->get_items(0, $maxitems);
	error_log('rss_items='.count($rss_items));
	$rsscount = 0;
		
	if ($content == '') {
		if ($template) {
			$content = $template;
		} else {
			$content = '<td>['.implode(']</td><td>[',$display_columns).']</td>';
			// Substitute any individual column templates
			foreach ($display_columns as $field)
				if (isset($options['template_'.$field]) && $options['template_'.$field])
					$content = str_replace('['.$field.']', $options['template_'.$field], $content);
		}
	}
	
	// Allow for a surrounding table so page html is still valid in the wordpress wysiwyg editor
	$matches = array();
	$thead = '';
	if (preg_match('/\s*<table>\s*(<thead>.*<\/thead>)?\s*(<tbody>)?\s*(<tr>)?\s*(.*?)\s*(<\/tr>)?\s*(<\/tbody>)?\s*<\/table>\s*/mis', $content, $matches)) {
		//error_log('matches='.print_r($matches, true));
		if (isset($matches[1])) {
			$thead = $matches[1];
		}
		$content = $matches[4];
	}
	
	if ($table)
		$content = '<tr>'.$content.'</tr>';
	
	// Display the headers
	if ($table) {
		$output .= '<table>';
		if ($thead) {
			$output .= $thead;
		} elseif ($header) {
			$output .= '<thead><tr>';
			foreach ($display_column_headers as $header) {
				$output .= '<th>'.$header.'</th>';
			}
			$output .= '</tr></thead>';
		}
		$output .= '<tbody>';
	}
	
	if ($offset > 0) {
		$cutoff_date = new DateTime();
		$cutoff_date = $cutoff_date->add(new DateInterval('P'.(is_numeric($offset) ? ($offset.'D') : $offset)));
	}
	// Pre-find all the substitutions and locations in the content to be repeated for each row
	$matches = array();
	preg_match_all('/\[('.str_replace(',','|',UPCAST_FIELD_NAMES).')\]/i', $content, $matches, PREG_OFFSET_CAPTURE);
	
	foreach ($rss_items as $item)
	{
		//error_log('content:"'.$content.'"');
		$selected = true;
		
		$item_enclosure = $item->get_enclosure();
		$item_author = $item->get_author();
		$item_category = $item->get_category();
		$item_subject = $item->get_item_tags(SIMPLEPIE_NAMESPACE_DC_11,'subject');
		$item_image = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ITUNES,'image');

		if ($selected && $offset > 0) {
			try {
				$this_date = date_create_from_format('Y-m-d G:i:s', $item->get_date('Y-m-d G:i:s'));
				$selected = ($this_date >= $cutoff_date);
			} catch (Exception $e) {
				error_log($e->getMessage());
				// Do nothing
			}
		}
		if ($selected && $skip > 0) {
			$selected = false;
			$skip--;
		}
		if ($selected && $category && $item_category) $selected = preg_match_simple($category,$item_category->get_label());
		if ($selected && $description) $selected = preg_match_simple($description,$item->get_description());
		if ($selected && $title) $selected = preg_match_simple($title,$item->get_title());
		if ($selected && $subject && is_array($item_subject) && count($item_subject)) $selected = preg_match_simple($subject,$item_subject[0]['data']);		
		if ($dates) {
			if ($selected && $pubdate) $selected = preg_match_simple($pubdate,$item->get_gmdate($dates));
			if ($selected && $date) $selected = preg_match_simple($date,$item->get_local_date($dates));
		}
		else {
			if ($selected && $pubdate) $selected = preg_match_simple($pubdate,$item->get_gmdate());
			if ($selected && $date) $selected = preg_match_simple($date,$item->get_local_date());
		}
		if ($selected && $author && $item_author) $selected = preg_match_simple($author,$item_author->get_name());
								
		if ($selected)
		{
			$rsscount++;
			if ($max && $rsscount > $max)
				break;
				
			if (isset($matches[1])) {
				$row = $content;
				// work through in reverse so substitutions do not affect earlier offsets
				for ($f = count($matches[1]) - 1; $f >= 0; $f--) {
					$text = '&nbsp;';
					switch ($matches[1][$f][0]) {
						case 'count':
							$text = $rsscount;
							break;
						case 'category':
							if ($item_category) $text = $item_category->get_label();
							break;
						case 'subject':
							if (is_array($item_subject) && count($item_subject)) $text = $item_subject[0]['data'];
							break;
						case 'description':
							$text = $item->get_description();
							break;
						case 'title':
							$text = $item->get_title();
							break;
						case 'date':
							try {
								$t = date_create_from_format('Y-m-d G:i:s', $item->get_local_date('%G-%m-%d %T'));							
								if ($t !== FALSE)
									$text = str_replace(' ', '&nbsp;', $t->format($dates));
							} catch (Exception $e) {
								error_log($e->getMessage());
								// Do nothing, text just defaults to a space
							}
							break;
						case 'pubdate':
							try {
								$t = date_create_from_format('Y-m-d G:i:s', $item->get_date('Y-m-d G:i:s'));							
								if ($t !== FALSE)
									$text = str_replace(' ', '&nbsp;', $t->format($dates));
							} catch (Exception $e) {
								error_log($e->getMessage());
								// Do nothing, text just defaults to a space
							}
							break;
						case 'author':
							if ($item_author && $item_author->get_name() != $rss_author_name) $text = $item_author->get_name();
							break;
						case 'url':
							$text = $item->get_link();
							break;						
						case 'link':
							$link = $item->get_link();
							if ($link)
								$text = '<a href="'.$item->get_link().'">'.$item->get_title().'</a>';
							break;
						case 'content':
							$text = $item->get_content();
							break;
						case 'guid':
							$text = $item->get_id();
							break;
						case 'image_url':
							if ($item_image) $text = $item_image[0]['attribs']['']['href'];
							break;						
						case 'image_link':
							if ($item_image) $text = '<img src="'.$item_image[0]['attribs']['']['href'].'">';
							break;
						case 'thumbnail_url':
						case 'thumb_url':
							if ($item_image) $text = str_replace('/images/','/thumbs/',$item_image[0]['attribs']['']['href']);
							break;
						case 'thumbnail':
						case 'thumb_link':
							if ($item_image) $text = '<img src="'.str_replace('/images/','/thumbs/',$item_image[0]['attribs']['']['href']).'">';
							break;													
						case 'embed':
							if ($item_enclosure) $text = $item_enclosure->embed();
							break;
						case 'bitrate':
							if ($item_enclosure) $text = $item_enclosure->get_bitrate();
							break;
						case 'duration':
							if ($item_enclosure) $text = $item_enclosure->get_duration(true);
							break;
						case 'sampling_rate':
							if ($item_enclosure) $text = $item_enclosure->get_sampling_rate();
							break;
						case 'native':
							if ($item_enclosure) $text = $item_enclosure->native_embed();
							break;
						case 'type':
							if ($item_enclosure) $text = $item_enclosure->get_type();
							break;
						case 'size':
							if ($item_enclosure) $text = number_format($item_enclosure->get_size(),1).' MB';
							break;
						case 'real_type':
							if ($item_enclosure) $text = $item_enclosure->get_real_type();
							break;
						case 'embed':
							if ($item_enclosure) $text = $item_enclosure->embed();
							break;		
					}
					$row = substr_replace($row, $text, $matches[1][$f][1] - 1, strlen($matches[1][$f][0]) + 2);
				}
				$output .= $row;
			} else {
				$output .= $content;
			}
		}
	}
	
	if ($table)
		$output .= '</tbody></table>';
	
	date_default_timezone_set($old_tz);
	
	$max = $rss->get_maxterm();
	return do_shortcode($output);
//	return do_shortcode($output . "Sortorder=" . $rss->get_sortorder() . ",Maxterm=$max");
}

if (is_admin())
	$upcast_settings_page = new UpcastSettingsPage();
	
// Now we set that function up to execute when the content is diplayed
add_shortcode('upcast', 'upcast_shortcode');
add_shortcode('upcast_rss', 'upcast_rss');
add_shortcode('upcast_image', 'upcast_image');
add_shortcode('upcast_thumbnail', 'upcast_thumbnail');
function upcast_filter($content) {
    $block = join("|",array("upcast","upcast_rss"));
    $rep = preg_replace("/(<p>)?\[($block)(\s[^\]]+)?\](<\/p>|<br \/>)?/","[$2$3]",$content);
    $rep = preg_replace("/(<p>)?\[\/($block)](<\/p>|<br \/>)?/","[/$2]",$rep);
return $rep;
}
add_filter("the_content", "upcast_filter");
// register Foo_Widget widget
//wp_register_style('upcastStylesheet', plugins_url('style.css', __FILE__) );
//function enqueue_upcast_styles() {
//   wp_enqueue_style('upcastStylesheet');
//}
function register_upcast_widget() {
    register_widget( 'UpCast_Widget' );
}
add_action( 'widgets_init', 'register_upcast_widget' );
//add_action( 'wp_enqueue_styles', 'enqueue_upcast_styles');

?>