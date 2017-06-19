<?php
/*
Plugin Name: Analyse Uploads
Plugin URI:  http://jamespark.ninja
Description: Get rid of unwanted and unused uploads
Version:     0.5
Author:      JamesPark.ninja
Author URI:  http://jamespark.ninja/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: jpn-analyse-uploads
Domain Path: /languages
*/

register_activation_hook(__FILE__, 'jpn_au_installation');
function jpn_au_installation() {
    $upload_dir = wp_upload_dir();
    
    $dirname = $upload_dir['basedir'].'/jpn-analyse-uploads';
    
    if (!file_exists($dirname)) { wp_mkdir_p( $dirname ); }
    
    jpn_the_whole_things_wrapped_in_a_box($dirname);
}

add_action("after_switch_theme", "jpn_the_whole_things_wrapped_in_a_box");
add_action( 'wp_ajax_nopriv_jpn_the_whole_things_wrapped_in_a_box', 'jpn_the_whole_things_wrapped_in_a_box' );
add_action( 'wp_ajax_jpn_the_whole_things_wrapped_in_a_box', 'jpn_the_whole_things_wrapped_in_a_box' );
function jpn_the_whole_things_wrapped_in_a_box($dirname = false, $ajax = false) {
    
    if (!$dirname) { 
        $upload_dir = wp_upload_dir(); 
        $dirname = $upload_dir['basedir'].'/jpn-analyse-uploads'; 
    }
    
    $theme = wp_get_theme(); $themeDir = get_template_directory();
    $newFile = $dirname.'/'.get_template().'.txt';
    
    if (file_exists($newFile)) { unlink($newFile); }
    
    $fileList = jpn_may_glob_have_mercy($themeDir);
    
    $out = fopen($newFile, "w");
    
    //Then cycle through the files reading and writing.
    foreach($fileList as $file){
        $file_content = file($themeDir.'/'.$file);
        foreach ($file_content as $line) {
            fwrite($out, $line);
        }
    }
    
    fclose($out);
    
    update_option('jpn_au_themefile', $newFile);
    
    if ($ajax) {
        $return['theme'] = $theme;
        $return['complete'] = true;
        echo json_encode($return);
        die();
    }
}

add_action( 'admin_enqueue_scripts', 'jpn_analyse_uploads_admin_enqueue_scripts' );
function jpn_analyse_uploads_admin_enqueue_scripts( $hook_suffix ) {
    // Enqueues jQuery
    wp_enqueue_script('jquery');
    
    wp_enqueue_style( 'jpn_triangle_grid', plugins_url('css/triangle.min.css', __FILE__ ), array(), '1.0.0', false);
    wp_enqueue_style( 'jpn_analyse_uploads_styles', plugins_url('css/jpn_analyse_uploads.css', __FILE__ ), array(), '1.0.0', false);
}

function jpn_how_big_is_it($file, $type){
    if (!$type || $type == 'file') {
        $file = esc_html(sanitize_text_field($file));
        if(file_exists($file)) { $bytes = filesize($file); } else { $bytes = 0; }
    } else {
        $bytes = intval($file, 10);
    }
    $s = array('b', 'Kb', 'Mb', 'Gb');
    $e = floor(log($bytes)/log(1024));
    return sprintf('%.2f '.$s[$e], ($bytes/pow(1024, floor($e))));
}

function jpn_attachment_id($url) {
    global $wpdb;
    $url = esc_url($url);
    $object = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE guid LIKE '$url';");
    return $object; 
}

function jpn_oh_my_glob($dir, $flags = 0) {
    $files = glob($dir.'/*.{*}', GLOB_BRACE);
	foreach(glob($dir.'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $glob){
        if (substr($glob, -19) != 'jpn-analyse-uploads') {
            $files = array_merge($files, jpn_oh_my_glob($dir.'/'.basename($glob)));
        }
	}
    return $files;
}

function jpn_may_glob_have_mercy($dir, $prefix = '') {
    $dir = rtrim($dir, '/');
    $result = array();

    foreach (glob("$dir/*", GLOB_MARK) as $f) {
        if (substr($f, -1) === '/') {
            $result = array_merge($result, jpn_may_glob_have_mercy($f, $prefix . basename($f) . '/'));
        } else {
            $result[] = $prefix . basename($f);
        }
    }
    return $result;
}

function jpn_one_nation_under_glob($term) {
    $file = get_option('jpn_au_themefile');
    $glob = false;
    $file_content = file($file);
    foreach ($file_content as $line) {
        if(strpos($line, $term) !== false) {
            $glob = true;
        }
    }
    return $glob;
}

function jpn_going_postal($url) {
    global $wpdb;
    $results = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type != 'revision' AND post_content REGEXP '$url';");
    return (!empty($results) ? true : false);
    //return $results;
}

function jpn_thats_so_meta($url, $id = false) {
    global $wpdb; $url = esc_url($url); $id = ($id ? esc_html(sanitize_text_field($id)) : false);
    $idQuery = ($id ? "OR meta_value LIKE '$id'" : "");
    $results = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE post_id != '1' AND (meta_value LIKE '$url' $idQuery);");
    return (!empty($results) ? true : false);
    //return $results;
}

add_action( 'wp_ajax_nopriv_jpn_where_my_files_at', 'jpn_where_my_files_at' );
add_action( 'wp_ajax_jpn_where_my_files_at', 'jpn_where_my_files_at' );
function jpn_where_my_files_at() {
    $object = wp_upload_dir(); $url = $object['baseurl']; $dir = $object['basedir'];
    $glob = jpn_oh_my_glob($dir);
    echo json_encode($glob);
    die();
}

add_action( 'wp_ajax_nopriv_jpn_do_the_thing', 'jpn_do_the_thing' );
add_action( 'wp_ajax_jpn_do_the_thing', 'jpn_do_the_thing' );
function jpn_do_the_thing() { 
    $time = microtime(true);
    $val = esc_html(sanitize_text_field($_POST['file']));
    
    $object = wp_upload_dir(); $dir = addslashes($object['basedir']); $url = $object['baseurl'];
    
    $path = str_replace($dir, '', $val); 
    
    $id = jpn_attachment_id($url.$path);
    $file['id'] = $id;
    $file['url'] = $val;
    $file['size'] = jpn_how_big_is_it($val, 'file');
    $file['actual'] = filesize($val);
    $file['theme'] = jpn_one_nation_under_glob($url.$path);
    $file['posts'] = jpn_going_postal($url.$path);
    $file['meta'] = jpn_thats_so_meta($url.$path, $id);
    $file['href'] = esc_url($url.$path);
    
    $file['time'] = microtime(true) - $time;
    
    echo json_encode($file);
    die();
}

function jpn_hello_is_it_me_youre_looking_for($id) {
    global $wpdb;
    $title_exists = false;
    $title_exists = $wpdb->get_results( 
        $wpdb->prepare( 
            "SELECT * FROM $wpdb->posts 
            WHERE ID = '$id' 
            AND post_type = 'attachment'"
        ) 
    );
    return $title_exists;
}

add_action( 'wp_ajax_nopriv_jpn_time_to_die', 'jpn_time_to_die' );
add_action( 'wp_ajax_jpn_time_to_die', 'jpn_time_to_die' );
function jpn_time_to_die() {    
    $fileList = $_POST['idlist'];
    $object = wp_upload_dir(); $dir = $object['basedir'];
    $files = explode(', ', $fileList);
    if (!empty($files)) {
        foreach ($files as $file) {
            $file = esc_html(sanitize_text_field($file));
            if (trim($file) != '') {
                if (substr( $file, 0, 6 ) === "JPNID:") {
                    $id = (int)str_replace('JPNID:', '', $file);
                    if (trim($id) != '') { 
                        if (jpn_hello_is_it_me_youre_looking_for($id)) { wp_delete_attachment($id, true); } 
                    }
                } else { unlink($file); }
            }
        }
    } else {
        if (trim($fileList) != '') {
            $fileList = esc_html(sanitize_text_field($fileList));
            if (substr( $fileList, 0, 6 ) === "JPNID:") {
                $id = (int)str_replace('JPNID:', '', $fileList);
                if (trim($id) != '') { 
                    if (jpn_hello_is_it_me_youre_looking_for($id)) { wp_delete_attachment($id, true); }
                }
            } else {
                if (file_exists($fileList)) { unlink($fileList); }
            }
        }
    }
    
    echo json_encode($return);
    die();
}

add_action( 'admin_menu', 'jpn_analyse_uploads_menu' );
function jpn_analyse_uploads_menu() { 
    add_menu_page( 'Analyse Uploads', 'Analyse Uploads', 'manage_options', 'jpn-analyse-uploads', 'jpn_analyse_uploads_options_page', 'dashicons-welcome-view-site' );
}

function jpn_analyse_uploads_options_page() { ?>

<div class="wrap" id="nexus_admin_options">   
    <div class="tri space" id="jpn_analyse_uploads_form_container">
        <div class="ang le-full">
            <div id="icon-themes" class="icon32"></div>  
            <h2>Analyse Uploads v0.5 &nbsp; | &nbsp; <a class="jpn_sub_title" href="https://github.com/JamesParkNINJA" target="_blank">JamesPark.NINJA</a> &nbsp; | &nbsp; <a class="jpn_donate jpn_sub_title" href="http://paypal.me/jamesparkninja/5" target="_blank">Like what I do? Donate me a beer! <i class="fa fa-beer" aria-hidden="true"></i></a></h2>
            <p>Analyses and allows you to remove unused media upload files.</p>
        </div>
        <div class="ang le-full">
            
            <div class="jpn_analysis__container">
                <div class="jpn_analysis__button hide-on-completion">
                    <div id="jpn-load"></div>
                    <a href="#" class="jpn-au-analysis" data-jpn-au-stage="1">
                        <span data-jpn-au-stage="1">START ANALYSIS <i class="fa fa-search" aria-hidden="true"></i></span>
                        <span data-jpn-au-stage="2">GENERATING FILES <i class="fa fa-cog fa-spin fa-fw"></i></span>
                        <div data-jpn-au-stage="3">
                            <p>Analysing files <span id="jpn-counter" data-count="0">0 / 0</span></p>
                            <div class="jpn-au-meter"><span></span></div>
                        </div>
                    </a>
                </div>
                <div class="jpn_results__container">
                    <a href="#" class="jpn-au-select-all hide-on-completion"><i class="fa fa-check-square" aria-hidden="true"></i> Toggle All</a>
                    <h3 class="hide-on-completion"><span class="jpn-unused">0</span> Unused "Upload" Files - Total: <span class="jpn-total"></span></h3>
                    <ul id="jpn_analyse_uploads_results" class="hide-on-completion"></ul>
                    <a class="jpn-au-empty-trash disabled" href="#">Remove Selected Files <i class="fa fa-trash" aria-hidden="true"></i><p>Saving <span class="jpn-au-saving">0 b</span></p></a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function jpn_lets_cut_these_bytes_down_to_size(bytes) {
        var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        if (bytes == 0) return '0 B';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    };
    
    function jpn_survey_says(results, end = false, length) {
        var html = '', count = parseInt(jQuery('#jpn-counter').attr('data-count'), 10); 
            if (count < length) { 
                count++; 
                var perc = (count / length) * 100,
                    //process = Math.ceil(parseFloat(results['time'])),
                    time = (length > 1000 ? length : Math.ceil(length / 2)),
                    mins = Math.floor(time / 60),
                    seconds = time - mins * 60,
                    estimate = (mins > 0 ? mins+' minutes '+seconds+' seconds' : seconds+' seconds');
                
                jQuery('#jpn-counter').html(count+' / '+length+' - roughly '+estimate).attr('data-count', count); 
                jQuery('.jpn-au-meter > span').css('width', perc+'%'); 
                if (!results['theme'] && !results['posts'] && !results['meta']) {
                    var html = '<li><input type="checkbox" id="jpn_upload_'+count+'" name="jpn_upload" value="'+(results['id'] ? 'JPNID:'+results['id'] : results['url'])+'" /> <label class="jpn-au-totals" for="jpn_upload_'+count+'" data-total="'+results['actual']+'">'+results['href'].split('\\').pop().split('/').pop()+' <span>'+results['size']+'</span></label></li>';
                    jQuery('#jpn_analyse_uploads_results').append(html);
                }
            }
        if (end) {
            var total = 0, unused = 0, number = jQuery('.jpn_results__container li').length;
            if (number > 0) {
                jQuery('.jpn-au-totals').each(function(){
                    total = total + parseFloat(jQuery(this).attr('data-total'), 10);
                });
                total = jpn_lets_cut_these_bytes_down_to_size(total);
                jQuery('.jpn-au-analysis').addClass('complete');
                jQuery('#jpn-load').removeClass('active');
                jQuery('.jpn-total').html(total);
                jQuery('.jpn-unused').html(number);
                jQuery('.jpn_results__container').addClass('active');
            } else {
                jQuery('.jpn_results__container').html('<h2 class="jpn-noFiles">No unused files, huzzah!</h2>').addClass('active');
            }
        }
    }
    
    function jpn_do_the_thing(array, num = 0, length) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {action: 'jpn_do_the_thing', file: array[num]},
            success: function(data) {
                var results = JSON.parse(data);
                console.log(results);
                if (num < length) { 
                    num++; 
                    jpn_do_the_thing(array, num, length);
                    jpn_survey_says(results, false, length); 
                }
                if (num == length) { jpn_survey_says(results, true, length); }
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.log(jqXHR);
            }
        });
    }
    
    function jpn_jesus_saves() {
        var saving = 0;
        if (jQuery('.jpn_results__container input:checkbox:checked').length > 0) {
            jQuery('.jpn-au-empty-trash.disabled').removeClass('disabled');
            jQuery('.jpn_results__container input:checkbox:checked ~ label').each(function(){
                saving = saving + parseFloat(jQuery(this).attr('data-total'), 10);
            });
        } else {
            jQuery('.jpn-au-empty-trash').addClass('disabled');
        }
        saving = jpn_lets_cut_these_bytes_down_to_size(saving);
        jQuery('.jpn-au-empty-trash p span').html(saving);
    }
    
    jQuery(document).on('change', '[name="jpn_upload"]', function(){
        jpn_jesus_saves();
    });
    
    jQuery(document).on('click', '.jpn-au-analysis', function(e){
        e.preventDefault();
        var stage = parseInt(jQuery(this).attr('data-jpn-au-stage'), 10);
        jQuery(this).attr('data-jpn-au-stage', stage+1);
        jQuery('.jpn_results__container.active').removeClass('active');
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {action: 'jpn_where_my_files_at'},
            success: function(data) {
                var files = JSON.parse(data);
                jQuery('.jpn-au-analysis').attr('data-jpn-au-stage', 3);
                jpn_do_the_thing(files, 0, files.length);
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.log(jqXHR);
            }
        });
    });
    
    jQuery(document).on('click', '.jpn-au-select-all', function(e){
        e.preventDefault();
        if (jQuery(this).hasClass('checked')) {
            jQuery(this).removeClass('checked');
            jQuery(document).find(':checkbox[name="jpn_upload"]').each(function(){
                jQuery(this).prop('checked', '');
            });
        } else {
            jQuery(this).addClass('checked');
            jQuery(document).find(':checkbox[name="jpn_upload"]').each(function(){
                jQuery(this).prop('checked', 'checked');
            });
        }
        jpn_jesus_saves();
    });
    
    jQuery(document).on('click', '.jpn_analysis__container:not(.reset) .jpn-au-empty-trash:not(.disabled)', function(e){
        e.preventDefault();
        var idList = false;
        jQuery('.jpn_analysis__container').addClass('deleting');
        jQuery('.jpn-au-empty-trash').html('DELETING<i class="fa fa-trash fa-spin fa-2x fa-fw" aria-hidden="true"></i>');
        jQuery(document).find(':checkbox[name="jpn_upload"]:checked').each(function(){
            var id = jQuery(this).val();
            idList = (idList ? idList+', '+id : id);
        });
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {action: 'jpn_time_to_die', idlist: idList},
            success: function(data) {
                var results = JSON.parse(data);
                jQuery('.jpn_analysis__container').removeClass('deleting').addClass('reset');
                jQuery('.jpn-au-empty-trash').html('DELETION COMPLETE!<span>RESET?</span>');
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.log(jqXHR);
            }
        });
    });
    
    jQuery(document).on('click', '.jpn_analysis__container.reset .jpn-au-empty-trash:not(.disabled)', function(e){
        e.preventDefault();
        location.reload();
    });
</script>

<?php } ?>