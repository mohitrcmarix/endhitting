<?php
namespace Elementor;
use \Elementor\ElementsKit_Widget_Advanced_Slider_Handler as Handler;
use \ElementsKit_Lite\Modules\Controls\Controls_Manager as ElementsKit_Controls_Manager;
use \ElementsKit_Lite\Modules\Controls\Widget_Area_Utils as Widget_Area_Utils;

if ( ! defined( 'ABSPATH' ) ) exit;

class ElementsKit_Widget_Advanced_Slider extends Widget_Base {
    use \ElementsKit_Lite\Widgets\Widget_Notice;

    public $base;

    public function get_name() {
        return Handler::get_name();
    }

    public function get_title() {
        return Handler::get_title();
    }

    public function get_icon() {
        return Handler::get_icon();
    }

    public function get_categories() {
        return Handler::get_categories();
    }

    public function get_help_url() {
        return '';
    }

	protected function register_controls() {

		$this->start_controls_section(
			'ekit_slider_general_section',
			[
				'label' => esc_html__('General', 'elementskit'),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'ekit_slider_title', [
				'label' => esc_html__('Title', 'elementskit'),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'ekit_slider_tab_content', [
				'label' => esc_html__('Content', 'elementskit'),
				'type' => ElementsKit_Controls_Manager::WIDGETAREA,
				'label_block' => true,
			]
		);

		$this->add_control(
			'ekit_slider_tab_items',
			[
				'label' => esc_html__('Tab content', 'elementskit'),
				'type' => Controls_Manager::REPEATER,
				'separator' => 'before',
				'title_field' => '{{ ekit_slider_title }}',
				'default' => [
					[
						'ekit_slider_title' => esc_html__('Slide One', 'elementskit'),
					],
					[
						'ekit_slider_title' => esc_html__('Slide Two', 'elementskit'),
					],
				],
				'fields' => $repeater->get_controls(),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ekit_slider_nav_control_section',
			[
				'label' => esc_html__('Slider Nav Controls', 'elementskit'),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'ekit_slider_effect_style',
			[
				'label' => esc_html__( 'Slider Effect Style', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => [
					'default'  => esc_html__( 'Default', 'elementskit' ),
					'fade'  => esc_html__( 'fade', 'elementskit' ),
					'cube' => esc_html__( 'cube', 'elementskit' ),
					'flip' => esc_html__( 'flip', 'elementskit' ),
					'coverflow' => esc_html__( 'coverflow', 'elementskit' ),
				],
			]
		);

		$this->add_control(
			'ekit_slider_show_pagination',
			[
				'label' => esc_html__( 'Show Pagination Controls', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'elementskit' ),
				'label_off' => esc_html__( 'Hide', 'elementskit' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'ekit_slider_nav_show_controls',
			[
				'label' => esc_html__( 'Show Nav Controls', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'elementskit' ),
				'label_off' => esc_html__( 'Hide', 'elementskit' ),
				'return_value' => 'yes',
				'default' => '',
			]
		);

		$this->add_control(
			'ekit_slider_nav_left_arrow_icon',
			[
				'label' => esc_html__( 'Left Arrow Icon', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'eicon-chevron-left',
					'library' => 'solid',
				],
				'condition' => ['ekit_slider_nav_show_controls' => 'yes']
			]
		);

		$this->add_control(
			'ekit_slider_nav_right_arrow_icon',
			[
				'label' => esc_html__( 'Right Arrow Icon', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'eicon-chevron-right',
					'library' => 'solid',
				],
				'condition' => ['ekit_slider_nav_show_controls' => 'yes']
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ekit_slider_nav_style_section',
			[
				'label' => esc_html__( 'Slider Nav Style', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => ['ekit_slider_nav_show_controls' => 'yes']
			]
		);

		$this->add_responsive_control(
			'ekit_slider_nav_icon_width',
			[
				'label' => esc_html__( 'width', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' , '%' ],
				'range' => [
					'%' => [
						'min' => -100,
						'max' => 200,
					],
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
			
				'selectors' => [
					'{{WRAPPER}} .swiper-nav-button' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_slider_nav_icon_height',
			[
				'label' => esc_html__( 'Height', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' , '%' ],
				'range' => [
					'%' => [
						'min' => -100,
						'max' => 200,
					],
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
			
				'selectors' => [
					'{{WRAPPER}} .swiper-nav-button' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs(
			'ekit_slider_nav_tabs'
		);

		$this->start_controls_tab(
			'ekit_slider_nav_tab_normal',
			[
				'label' => esc_html__('Normal', 'elementskit'),
			]
		);

		$this->add_control(
			'ekit_slider_nav_icon_color',
			[
				'label' => esc_html__( 'Icon Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default'	=> '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'ekit_slider_nav_icon_bg_color',
			[
				'label' => esc_html__( 'Background Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default'	=> '#101010',
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button' => 'background: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'ekit_slider_nav_border',
				'label' => esc_html__( 'Border', 'elementskit' ),
				'selector' => '{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'ekit_slider_nav_tab_hover',
			[
				'label' => esc_html__('Hover', 'elementskit'),
			]
		);

		$this->add_control(
			'ekit_slider_nav_icon_color_hover',
			[
				'label' => esc_html__( 'Icon Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button:hover' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'ekit_slider_nav_icon_bg_color_hover',
			[
				'label' => esc_html__( 'Background Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button:hover' => 'background: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'ekit_slider_nav_border_hover',
				'label' => esc_html__( 'Border', 'elementskit' ),
				'selector' => '{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button:hover',
			]
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control(
			'ekit_slider_nav_typography_divider',
			[
				'type' => Controls_Manager::DIVIDER,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'ekit_slider_nav_icon_typography',
				'label' => esc_html__( 'Typography', 'elementskit' ),
				'selector' => '{{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button',
			]
		);

		$this->add_responsive_control(
			'ekit_slider_nav_border_radius',
			[
				'label' => esc_html__( 'Nav Border Radius (px)', 'elementskit' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-nav-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'ekit_slider_pagination_section',
			[
				'label' => esc_html__( 'Slider Pagination Style', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => ['ekit_slider_show_pagination' => 'yes']
			]
		);

		$this->add_control(
			'ekit_slider_pagination_make_vertical',
			[
				'label' => esc_html__( 'Make Pagination Vertical?', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'inline-block',
				'options' => [
					'block' => esc_html__( 'Yes', 'elementskit' ),
					'inline-block' => esc_html__( 'No', 'elementskit' ),
				],
                'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination-bullet' => 'display: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'ekit_slider_pagination_bg_color',
			[
				'label' => esc_html__( 'Pagination Background Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination-bullet' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_slider_pagination_size',
			[
				'label' => esc_html__( 'Size of Bullet Point (px)', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
			
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination-bullet' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'ekit_slider_pagination_align_inline',
			[
				'label' => esc_html__( 'Pagination Alignment', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left:0' => [
						'title' => esc_html__( 'Left', 'elementskit' ),
						'icon' => 'eicon-text-align-left',
					],
					'left: 50%;transform: translateX(-50%);' => [
						'title' => esc_html__( 'Center', 'elementskit' ),
						'icon' => 'eicon-text-align-center',
					],
					'right:0;left:inherit;' => [
						'title' => esc_html__( 'Right', 'elementskit' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'left: 50%;transform: translateX(-50%);',
				'toggle' => true,
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination' => '{{VALUE}}',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_slider_bottom_to_top',
			[
				'label' => esc_html__( 'Bottom To Top (px)', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
			
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination' => 'bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_slider_pagination_border_radius',
			[
				'label' => esc_html__( 'Border Radius (px)', 'elementskit' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination-bullet' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_slider_pagination_margin',
			[
				'label' => esc_html__( 'Pagination Gap (px)', 'elementskit' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors' => [
					'{{WRAPPER}} .elementskit-advanced-slider .swiper-pagination-bullet' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render( ) {
		echo '<div class="ekit-wid-con" >';
			$this->render_raw();
		echo '</div>';
	}

	protected function render_raw( ) {
		$settings = $this->get_settings_for_display();
		extract($settings);
		?>

		<div class="elementskit-advanced-slider"  data-widget_settings='<?php echo json_encode($settings); ?>'>

			<div class="ekit-slider-wrapper" data-effect="<?php echo esc_attr($ekit_slider_effect_style); ?>">
				<div class="swiper-wrapper">
					<?php foreach ($ekit_slider_tab_items as $i => $tab) : ?>
						<div class="swiper-slide elementor-repeater-item-<?php echo esc_attr( $tab[ '_id' ] ); ?>">
							<?php echo Widget_Area_Utils::parse( $tab['ekit_slider_tab_content'], $this->get_id(), $tab[ '_id' ], '', ($i + 1) ); ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if($ekit_slider_nav_show_controls === 'yes') : ?>
					<!-- next / prev arrows -->
					<div class="swiper-nav-button swiper-button-next"> 
						<?php \Elementor\Icons_Manager::render_icon( $ekit_slider_nav_right_arrow_icon, [ 'aria-hidden' => 'true' ] ); ?>
					</div>
					<div class="swiper-nav-button swiper-button-prev">
						<?php \Elementor\Icons_Manager::render_icon( $ekit_slider_nav_left_arrow_icon, [ 'aria-hidden' => 'true' ] ); ?>
					</div>
					<!-- !next / prev arrows -->
				<?php endif; ?>

				<?php if($ekit_slider_show_pagination === 'yes') : ?>
						<div class="swiper-pagination"></div>
				<?php endif; ?>
			</div>

		</div>

		<?php
	}

	protected function content_template() { }
}