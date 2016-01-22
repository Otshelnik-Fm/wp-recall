<?phpglobal $wpdb;if(!defined('RMAG_PREF')) define('RMAG_PREF', $wpdb->prefix."rmag_");require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );    $collate = '';if ( $wpdb->has_cap( 'collation' ) ) {    if ( ! empty( $wpdb->charset ) ) {        $collate .= "DEFAULT CHARACTER SET $wpdb->charset";    }    if ( ! empty( $wpdb->collate ) ) {        $collate .= " COLLATE $wpdb->collate";    }}$table = RMAG_PREF ."users_balance";if($wpdb->get_var("show tables like '". RMAG_PREF ."user_count'") == RMAG_PREF ."user_count") {    //удаляем все данные с нулевыми значениями    $wpdb->query("DELETE FROM ". RMAG_PREF ."user_count WHERE count='0'");    //переименовываем таблицу `_user_count`    $wpdb->query("ALTER TABLE ". RMAG_PREF ."user_count RENAME $table");    //удаляем столбец `ID`    $wpdb->query("ALTER TABLE $table DROP COLUMN ID");    //переименовываем столбец `user` и назначаем его уникальным ключом    $wpdb->query("ALTER TABLE $table CHANGE user user_id INT( 20 ) NOT NULL");    $wpdb->query("ALTER IGNORE TABLE $table ADD UNIQUE KEY (user_id)");    //переименовываем столбец `count`    $wpdb->query("ALTER TABLE $table CHANGE count user_balance VARCHAR( 20 ) NOT NULL");}else{    $sql = "CREATE TABLE IF NOT EXISTS ". $table . " (                user_id INT(20) NOT NULL,                user_balance VARCHAR (20) NOT NULL,                PRIMARY KEY user_id (user_id)              ) $collate;";    dbDelta( $sql );    }    $table = RMAG_PREF ."pay_results";$sql = "CREATE TABLE IF NOT EXISTS ". $table . " (        ID bigint (20) NOT NULL AUTO_INCREMENT,        inv_id INT(20) NOT NULL,        user INT(20) NOT NULL,        count INT(20) NOT NULL,        time_action DATETIME NOT NULL,        PRIMARY KEY id (id),        KEY inv_id (inv_id),        KEY user (user),      ) $collate;";dbDelta( $sql );