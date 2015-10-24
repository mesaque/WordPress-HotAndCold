<?php
/*
Plugin Name: WordPress Hot & Cold Relation Database
Plugin URI:  https://github.com/mesaque/WordPress-HotAndCold
Description: This Plugin implements a relation of archiving posts and current most accessed ones
Version:     1.0
Author:      Mesaque Silva
Author URI:  https://github.com/mesaque
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
add_filter( 'query', 'hot_cold_database_logic' );
register_activation_hook( __FILE__, 'mysql_setup' );
register_deactivation_hook( __FILE__, 'mysql_drop_setup' );

function hot_cold_database_logic( $query ){
	global $wpdb, $pagenow;

	if ( ( strpos( $query, sprintf( '%sposts' , $wpdb->prefix ) ) ) === false ) return $query;
	if ( strpos( $query, 'SQL_CALC_FOUND_ROWS' ) !== false ) return $query;
	if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) return $query;

	$query_posts     = $query;
	$limiters_orders = '';

	#find ORDER BY and|or LIMIT clauses and remove it from main query
	if( ( $position_order_by = strpos( $query, 'ORDER BY' ) ) !== false ):
		#first filter remove order by and so on
		$query_posts     = substr( $query, 0,  $position_order_by );
		#hold rest of query
		$limiters_orders = str_replace( sprintf( '%sposts.' , $wpdb->prefix ) , '', substr( $query, $position_order_by ) );
	elseif ( ( $position_limit = strpos( $query, 'LIMIT' ) ) !== false ):
		#first filter remove order by and so on
		$query_posts     = substr( $query, 0,  $position_limit );
		$limiters_orders = substr( $query, $position_limit );
	endif;

	#second filter, create a string query from wp_posts to wp_posts_hot
	$query_hot_posts = str_replace( sprintf( '%sposts' , $wpdb->prefix ) , sprintf( '%sposts_hot' , $wpdb->prefix ) , $query_posts );

	#third filter get only collumns used in order by for final query
	$columns_from_order = $limiters_orders;
	if ( ( $limit =  strpos( $limiters_orders, 'LIMIT' ) ) !== false ) $columns_from_order = substr( $limiters_orders, 0,  $limit );
	$columns_from_order = preg_replace( '#DESC|ASC|ORDER BY#', '', $columns_from_order );


	if ( $columns_from_order != '' ):
		if (  strpos( $query_hot_posts, '*' ) === false  ):
		$query_hot_posts =  preg_replace('#FROM#', sprintf(',%s FROM', $columns_from_order ), $query_hot_posts, 1);
		$query_posts     =  preg_replace('#FROM#', sprintf(',%s FROM', $columns_from_order ), $query_posts, 1);
		endif;
	endif;

	if ( strpos( $limiters_orders, '.') !== false ):
		$limiters_orders = preg_replace( "# [a-z\_?]*\.#i", ' ', $limiters_orders );
	endif;

	$query = <<<SQL
	(
		$query_hot_posts
	)
	UNION ALL
	(
		$query_posts
		AND NOT EXISTS (
			$query_hot_posts
			)
	)
	$limiters_orders
SQL;
	return $query;
}

function mysql_setup()
{
	global $wpdb;

	$date = new DateTime();
	$modified = $date->modify( '-3 month' );
	$formated = $modified->format('Ym');

	$statment = <<<SQL
	CREATE TABLE IF NOT EXISTS {$wpdb->prefix}posts_hot LIKE {$wpdb->prefix}posts;

	TRUNCATE {$wpdb->prefix}posts_hot;
	INSERT INTO {$wpdb->prefix}posts_hot(`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) SELECT `ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count` from {$wpdb->prefix}posts  where extract( YEAR_MONTH from post_date) > {$formated};

	DROP TRIGGER IF EXISTS Tgr_InsertOnHOT;
	DROP TRIGGER IF EXISTS Tgr_UpdateOnHOT;
	DROP TRIGGER IF EXISTS Tgr_DeleteOnHOT;
	DROP EVENT IF EXISTS Evn_remove_old_posts_hot;
	CREATE TRIGGER Tgr_InsertOnHOT AFTER INSERT
	ON {$wpdb->prefix}posts
	FOR EACH ROW
	BEGIN
	INSERT INTO {$wpdb->prefix}posts_hot (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) values (NEW.ID, NEW.post_author, NEW.post_date, NEW.post_date_gmt, NEW.post_content, NEW.post_title, NEW.post_excerpt, NEW.post_status, NEW.comment_status, NEW.ping_status, NEW.post_password, NEW.post_name, NEW.to_ping, NEW.pinged, NEW.post_modified, NEW.post_modified_gmt, NEW.post_content_filtered, NEW.post_parent, NEW.guid, NEW.menu_order, NEW.post_type, NEW.post_mime_type, NEW.comment_count);
	END;

	CREATE TRIGGER Tgr_UpdateOnHOT AFTER UPDATE
	ON {$wpdb->prefix}posts
	FOR EACH ROW
	BEGIN
	UPDATE {$wpdb->prefix}posts_hot SET  post_author = NEW.post_author, post_date = NEW.post_date, post_date_gmt = NEW.post_date_gmt, post_content = NEW.post_content, post_title = NEW.post_title, post_excerpt = NEW.post_excerpt, post_status = NEW.post_status, comment_status = NEW.comment_status, ping_status = NEW.ping_status, post_password = NEW.post_password, post_name = NEW.post_name, to_ping = NEW.to_ping, pinged = NEW.pinged, post_modified = NEW.post_modified, post_modified_gmt = NEW.post_modified_gmt, post_content_filtered = NEW.post_content_filtered, post_parent = NEW.post_parent, guid = NEW.guid, menu_order = NEW.menu_order, post_type = NEW.post_type, post_mime_type = NEW.post_mime_type , comment_count = NEW.comment_count  WHERE  ID = NEW.ID;
	END;

	CREATE TRIGGER Tgr_DeleteOnHOT AFTER DELETE
	ON {$wpdb->prefix}posts
	FOR EACH ROW
	BEGIN
	DELETE from {$wpdb->prefix}posts_hot WHERE ID  = OLD.ID;
	END;

	CREATE EVENT  Evn_remove_old_posts_hot ON SCHEDULE EVERY 2 WEEK
	DO
	BEGIN
	DELETE from {$wpdb->prefix}posts_hot WHERE post_date < DATE_SUB( NOW(), INTERVAL 90 DAY );
	END;
SQL;

	mysqli_multi_query($wpdb->dbh,$statment);
}
function mysql_drop_setup()
{
	global $wpdb;
	$statment = <<<SQL
		DROP TABLE IF EXISTS {$wpdb->prefix}posts_hot;
		DROP TRIGGER IF EXISTS Tgr_InsertOnHOT;
		DROP TRIGGER IF EXISTS Tgr_UpdateOnHOT;
		DROP TRIGGER IF EXISTS Tgr_DeleteOnHOT;
		DROP EVENT IF EXISTS Evn_remove_old_posts_hot;
SQL;
	mysqli_multi_query($wpdb->dbh,$statment);
}