<?php

class Rcl_Users{

    public $number = false;
    public $inpage = 10;
    public $offset = 0;
    public $paged = 0;
    public $orderby = 'user_registered';
    public $order = 'DESC';
    public $template = 'rows';
    public $include = '';
    public $exclude = '';
    public $usergroup = '';
    public $group_id = '';
    public $only = false;
    public $filters = 0;
    public $search_form = 1;
    public $query_count = false;
    public $users_count = 0;
    public $data;
    public $add_uri;
    public $relation = 'AND';

    function __construct($args){

        $this->init_properties($args);

        if(isset($_GET['users-filter'])&&$this->filters) $this->orderby = $_GET['users-filter'];
        if(isset($_GET['users-order'])&&$this->filters) $this->order = $_GET['users-order'];

        $this->add_uri['users-filter'] = $this->orderby;

        $this->data = ($this->data)? array_map('trim', explode(',',$this->data)): array();

        //print_r($this);

        if($this->data('description'))
            add_filter('rcl_users',array($this,'add_descriptions'));

        //получаем данные рейитнга
        if($this->orderby=='rating_total')
            add_filter('rcl_users_query',array($this,'add_query_rating_total'));
        else if($this->data('rating_total'))
            add_filter('rcl_users',array($this,'add_rating_total'));

        //считаем публикации
        if($this->orderby=='posts_count')
            add_filter('rcl_users_query',array($this,'add_query_posts_count'));
        else if($this->data('posts_count'))
            add_filter('rcl_users',array($this,'add_posts_count'));

        //считаем комментарии
        if($this->orderby=='comments_count')
            add_filter('rcl_users_query',array($this,'add_query_comments_count'));
        else if($this->data('comments_count'))
            add_filter('rcl_users',array($this,'add_comments_count'));

        if($this->orderby=='time_action')
            add_filter('rcl_users_query',array($this,'add_query_time_action'));
        else
            add_filter('rcl_users',array($this,'add_time_action'));


        if($this->usergroup){
            add_filter('rcl_users_query',array($this,'add_query_usergroup'));
        }

        if($this->filters)
            add_filter('rcl_users_query',array($this,'add_query_search'));
    }

    function remove_userdata(){
        remove_all_filters('rcl_users_query');
        remove_all_filters('rcl_users');
    }

    function init_properties($args){

        $properties = get_class_vars(get_class($this));

        foreach ($properties as $name=>$val){
            if(isset($args[$name])) $this->$name = $args[$name];
        }
    }

    function data($needle){
        if(!$this->data) return false;
        $key = array_search($needle, $this->data);
        return (false!==$key)? true: false;
    }

    function get_users($args = false){
        global $wpdb;

        if($args) $this->init_properties($args);

        $users = $wpdb->get_results( $this->query() );

        //if($this->number) $this->users_count = count($users);

        $users = apply_filters('rcl_users',$users);

        return $users;
    }

    function count_users(){
        global $wpdb;
        if($this->number){
            $users = $this->get_users();
            return count($users);
        }else{
            return $wpdb->get_var( $this->query('count') );
        }
    }

    function setup_userdata($userdata){
        global $rcl_user;
        $rcl_user = (object)$userdata;
        return $rcl_user;
    }

    function search_request(){
        global $user_LK;

        $rqst = '';

        if(isset($_GET['search-user'])||$user_LK){
            $rqst = array();
            foreach($_GET as $k=>$v){
                if($k=='navi'||$k=='users-filter') continue;
                $rqst[$k] = $k.'='.$v;
            }

        }

        if($this->add_uri){
            foreach($this->add_uri as $k=>$v){
                $rqst[$k] = $k.'='.$v;
            }
        }

        $rqst = apply_filters('rcl_users_uri',$rqst);

        return $rqst;
    }

    function query($count=false){
        global $wpdb,$active_addons,$rcl_options;

        if($count) $this->query_count = true;

        $query = array(
            'select'    => array(),
            'join'      => array(),
            'where'     => array(),
            'relation'     => $this->relation,
            'group'     => '',
            'orderby'   => ''
        );

        if($count){

            $query['select'] = array(
                "COUNT(users.ID)"
            );

        }else{

            $query['select'] = array(
                "users.ID"
              , "users.display_name"
            );

            if($this->data('user_registered')||
            $this->orderby=='user_registered'
            ) $query['select'][] = "users.user_registered";

        }

        if($this->include) $query['where'][] = "users.ID IN ($this->include)";
        if($this->exclude) $query['where'][] = "users.ID NOT IN ($this->exclude)";

        if($this->only=='action'){
            $timeout = ($rcl_options['timeout'])? $rcl_options['timeout']: 10;
            $query['where'][] = "actions.time_action > date_sub('".current_time('mysql')."', interval $timeout minute)";
        }

        $query = apply_filters('rcl_users_query',$query);

        $query_string = "SELECT "
            . implode(", ",$query['select'])." "
            . "FROM $wpdb->users AS users "
            . implode(" ",$query['join'])." ";

        if($query['where']) $query_string .= "WHERE ".implode(' '.$query['relation'].' ',$query['where'])." ";
        if($query['group']) $query_string .= "GROUP BY ".$query['group']." ";

        if(!$this->query_count){
            if(!$query['orderby']) $query['orderby'] = "users.".$this->orderby;
            $query_string .= "ORDER BY ".$query['orderby']." $this->order ";
            $query_string .= "LIMIT $this->offset,$this->number";
        }
        //if(!$count) echo $query_string;

        if($this->query_count)
            $this->query_count = false;

        return $query_string;

    }

    //добавляем данные полей профиля, если перечислены через usergroup
    function add_query_usergroup($query){
        global $wpdb;

        $usergroup = explode('|',$this->usergroup);
        $a = 0;
        foreach($usergroup as $k=>$filt){
            $f = explode(':',$filt);
            $n = 'metas_'.++$a;
            $query['join'][] = "INNER JOIN $wpdb->usermeta AS $n ON users.ID=$n.user_id";
            $query['where'][] = "($n.meta_key='$f[0]' AND $n.meta_value='$f[1]')";
        }
        return $query;
    }

    //добавляем выборку данных активности пользователей в основной запрос
    function add_query_time_action($query){
        global $wpdb;

        if(!$this->query_count){
            $query['select'][] = "actions.time_action";
            $query['orderby'] = "(CASE WHEN actions.$this->orderby IS NULL then users.user_registered ELSE actions.$this->orderby END)";
        }

        $query['join'][] = "RIGHT JOIN ".RCL_PREF."user_action AS actions ON users.ID=actions.user";
        return $query;
    }

    //добавление данных активности пользователей после основного запроса
    function add_time_action($users){
        global $wpdb;

        $ids = $this->get_users_ids($users);

        $query = "SELECT time_action, user AS ID "
                . "FROM ".RCL_PREF."user_action "
                . "WHERE user IN (".implode(',',$ids).")";

        $posts = $wpdb->get_results($query);

        if($posts)
            $users = $this->merge_objects($users,$posts,'time_action');

        return $users;
    }

    //добавляем выборку данных постов в основной запрос
    function add_query_posts_count($query){
        global $wpdb;

        if(!$this->query_count){
            $query['select'][] = "posts.posts_count";
            $query['orderby'] = "posts.posts_count";
        }

        $query['join'][] = "INNER JOIN (SELECT COUNT(post_author) AS posts_count, post_author "
                . "FROM $wpdb->posts "
                . "WHERE post_status='publish' "
                . "GROUP BY post_author) posts "
                . "ON users.ID=posts.post_author";

        return $query;
    }

    //добавление данных публикаций после основного запроса
    function add_posts_count($users){
        global $wpdb;

        $ids = $this->get_users_ids($users);

        $query = "SELECT COUNT(post_author) AS posts_count, post_author AS ID "
                . "FROM $wpdb->posts "
                . "WHERE post_status = 'publish' AND post_author IN (".implode(',',$ids).") "
                . "GROUP BY post_author";

        $posts = $wpdb->get_results($query);

        if($posts)
            $users = $this->merge_objects($users,$posts,'posts_count');

        return $users;
    }

    //добавляем выборку данных комментариев в основной запрос
    function add_query_comments_count($query){
        global $wpdb;

        if(!$this->query_count){
            $query['select'][] = "comments.comments_count";
            $query['orderby'] = "comments.comments_count";
        }

        $query['join'][] = "INNER JOIN (SELECT COUNT(user_id) AS comments_count, user_id "
                . "FROM $wpdb->comments "
                . "GROUP BY user_id) comments "
                . "ON users.ID=comments.user_id";

        return $query;
    }

    //добавление данных комментариев после основного запроса
    function add_comments_count($users){
        global $wpdb;

        $ids = $this->get_users_ids($users);

        $query = "SELECT COUNT(user_id) AS comments_count, user_id AS ID "
                . "FROM $wpdb->comments "
                . "WHERE user_id IN (".implode(',',$ids).") "
                . "GROUP BY user_id";

        $comments = $wpdb->get_results($query);

        if($comments)
            $users = $this->merge_objects($users,$comments,'comments_count');

        return $users;
    }

    //добавление данных статуса после основного запроса
    function add_descriptions($users){
        global $wpdb;

        $ids = $this->get_users_ids($users);

        $query = "SELECT meta_value AS description, user_id AS ID "
                . "FROM $wpdb->usermeta "
                . "WHERE user_id IN (".implode(',',$ids).") AND meta_key='description'";

        $descs = $wpdb->get_results($query);

        if($descs)
            $users = $this->merge_objects($users,$descs,'description');

        return $users;
    }

    //добавляем выборку данных рейтинга в основной запрос
    function add_query_rating_total($query){

        if(!$this->query_count){
            $query['select'][] = "ratings.rating_total";
            $query['group'] = "ratings.user_id";
            $query['orderby'] = "(CASE WHEN CAST(ratings.$this->orderby AS DECIMAL) IS NULL then 0 ELSE CAST(ratings.$this->orderby AS DECIMAL) END)";
        }

        $query['join'][] = "LEFT JOIN ".RCL_PREF."rating_users AS ratings ON users.ID=ratings.user_id";

        return $query;
    }

    //добавление данных рейтинга после основного запроса
    function add_rating_total($users){
        global $wpdb;

        $ids = $this->get_users_ids($users);

        $query = "SELECT rating_total, user_id AS ID "
                . "FROM ".RCL_PREF."rating_users "
                . "WHERE user_id IN (".implode(',',$ids).")";

        $descs = $wpdb->get_results($query);

        if($descs)
            $users = $this->merge_objects($users,$descs,'rating_total');

        return $users;
    }

    function get_users_ids($users){
        $ids = array();

        foreach($users as $user){
            $ids[] = $user->ID;
        }

        return $ids;
    }

    function merge_objects($users,$data,$key){
        foreach($users as $k=>$user){
            foreach($data as $d){
                if($d->ID!=$user->ID) continue;
                $users[$k]->$key = $d->$key;
            }
        }
        return $users;
    }

    function get_filters($count_users = false){
        global $post,$user_LK,$active_addons;

        if(!$this->filters) return false;

        $content = '';

        if($this->search_form) $content = apply_filters('users_search_form_rcl',$content);

        $count_users = (false!==$count_users)? $count_users: $this->count_users();

        $content .='<h3>'.__('Total users','rcl').': '.$count_users.'</h3>';

        if(isset($this->add_uri['users-filter'])) unset($this->add_uri['users-filter']);

        $s_array = $this->search_request();

        $rqst = ($s_array)? implode('&',$s_array).'&' :'';

        $url = ($user_LK)? get_author_posts_url($user_LK): get_permalink($post->ID);

        $perm = rcl_format_url($url).$rqst;

        $filters = array(
            'time_action'       =>__('Activity','rcl'),
            'posts_count'       =>__('Publications','rcl'),
            'comments_count'    =>__('Comments','rcl'),
            'user_registered'   =>__('Registration','rcl'),
        );

        if(isset($active_addons['rating-system']))
                $filters['rating_total'] = __('Rated','rcl');

        $filters = apply_filters('rcl_users_filter',$filters);

        $content .= '<div class="rcl-user-filters">'.__('Filter by','rcl').': ';

        foreach($filters as $key=>$name){
            $content .= $this->get_filter($key,$name,$perm);
        }

        $content .= '</div>';

        return $content;

    }

    function get_filter($key,$name,$perm){
        return '<a class="user-filter '.rcl_a_active($this->orderby,$key).'" href="'.$perm.'users-filter='.$key.'">'.$name.'</a> ';
    }

    function add_query_search($query){
            if(!$search_field)
            $search_text = (isset($_GET['search_text']))? sanitize_user($_GET['search_text']): '';
            $search_field = (isset($_GET['search_field']))? $_GET['search_field']: '';
            if(!$search_text||!$search_field) return $query;
            $query['where'][] = "users.$search_field LIKE '%$search_text%'";
            return $query;
    }
}
