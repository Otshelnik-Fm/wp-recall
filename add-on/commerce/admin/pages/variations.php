<?php

global $wpdb;

rcl_sortable_scripts();

if ( ! class_exists( 'Rcl_EditFields' ) ) 
    include_once RCL_PATH.'functions/class-rcl-editfields.php';

$f_edit = new Rcl_EditFields(
        'products-variations',
        array(
            //'sortable'=>false,
            //'meta-key'=>false,
            'custom-slug'=>1,
            'fields' => array(
                'select',
                'checkbox',
                'radio'
            )
        ));

$content = '<h2>'.__('Products variations management','wp-recall').'</h2>';

$content .= $f_edit->edit_form(array(
                $f_edit->option('textarea',array(
                    'name'=>'notice',
                    'label'=>__('field description','wp-recall')
                ))
            ));

echo $content;
