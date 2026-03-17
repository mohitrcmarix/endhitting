<?php
namespace Elementor;


class ElementsKit_Widget_Whatsapp_Handler extends \ElementsKit_Lite\Core\Handler_Widget{

    static function get_name() {
        return 'elementskit-whatsapp';
    }

    static function get_title() {
        return esc_html__( 'WhatsApp', 'elementskit' );
    }

    static function get_icon() {
        return 'ekit ekit-whatsapp ekit-widget-icon';
    }

    static function get_categories() {
        return [ 'elementskit' ];
    }

    static function get_dir() {
        return \ElementsKit_Lite::widget_dir() . 'whatsapp/';
    }

    static function get_url() {
        return \ElementsKit_Lite::widget_url() . 'whatsapp/';
    }

}