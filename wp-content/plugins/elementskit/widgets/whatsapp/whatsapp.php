<?php

namespace Elementor;

use \Elementor\ElementsKit_Widget_Whatsapp_Handler as Handler;
use ElementsKit_Lite;
use \ElementsKit_Lite\Modules\Controls\Controls_Manager as ElementsKit_Controls_Manager;

if (!defined('ABSPATH')) exit;

class ElementsKit_Widget_Whatsapp extends Widget_Base
{
	use \ElementsKit_Lite\Widgets\Widget_Notice;

	public $base;

	public function get_name()
	{
		return Handler::get_name();
	}

	public function get_title()
	{
		return Handler::get_title();
	}

	public function get_icon()
	{
		return Handler::get_icon();
	}

	public function get_categories()
	{
		return Handler::get_categories();
	}

	public function get_help_url()
	{
		return '';
	}

	protected function register_controls()
	{
		$this->start_controls_section(
			'user_tab_section',
			[
				'label' => esc_html__('Header', 'elementskit'),
			]
		);

		$this->add_control(
			'whatsapp_user_image',
			[
				'label' => esc_html__('Choose Profile Photo', 'elementskit'),
				'type' => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => ElementsKit_Lite::widget_url().'init/assets/img/whatsapp_user.svg',
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'whatsapp_username',
			[
				'label' => esc_html__('Username', 'elementskit'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('John Doe', 'elementskit'),
				'placeholder' => esc_html__('Type your title here', 'elementskit'),
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'whatsapp_user_text',
			[
				'label' => esc_html__('User Text', 'elementskit'),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => esc_html__('Typically replies within a day', 'elementskit'),
				'placeholder' => esc_html__('Type your text here', 'elementskit'),
				'dynamic' => [
					'active' => true,
				],
			]
		);
		$this->end_controls_section();
		$this->start_controls_section(
			'body_tab_section',
			[
				'label' => esc_html__('Body', 'elementskit'),
			]
		);

		$this->add_control(
			'whatsapp_asking_text',
			[
				'label' => esc_html__('Asking Text', 'elementskit'),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => esc_html__('Hey, Do you want to talk with us?', 'elementskit'),
				'placeholder' => esc_html__('Type your text here', 'elementskit'),
				'dynamic' => [
					'active' => true,
				],
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'footer_tab_section',
			[
				'label' => esc_html__('Footer', 'elementskit'),
			]
		);

		$this->add_control(
			'whatsapp_input_placeholder',
			[
				'label' => esc_html__('Input Placeholder', 'elementskit'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__('Write Something', 'elementskit'),
				'placeholder' => esc_html__('Type your text here', 'elementskit'),
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'settings_tab_section',
			[
				'label' => esc_html__('Settings', 'elementskit'),
			]
		);

		$this->add_control(
			'whatsapp_number',
			[
				'label' => esc_html__('Whatsapp Number', 'elementskit'),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => esc_html__('+880175555555', 'elementskit'),
				'placeholder' => esc_html__('Type your whatsapp number', 'elementskit'),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'show_from_first',
			[
				'label' => esc_html__( 'Show From First', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'elementskit' ),
				'label_off' => esc_html__( 'Hide', 'elementskit' ),
				'return_value' => 'yes',
				'default' => '',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'whatsapp_btn_style_section',
			[
				'label' => esc_html__( 'Sticky Button', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'ekit_whatsapp_sticky_btn_width',
			[
				'label' => esc_html__( 'Button Width (px)', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 50,
				],
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp .elementskit-whatsapp__popup--btn' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'ekit_whatsapp_sticky_btn_height',
			[
				'label' => esc_html__( 'Button Height (px)', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 50,
				],
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp .elementskit-whatsapp__popup--btn' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'ekit_whatsapp_sticky_btn_bg',
			[
				'label' => esc_html__( 'Button Background', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__popup--btn' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'ekit_whatsapp_sticky_btn_icon_color',
			[
				'label' => esc_html__( 'Icon Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .whatsapp-rotate-icon path' => 'stroke: {{VALUE}}',
				],
				'seperator' => 'before',
			]
		);

		$this->add_responsive_control(
			'ekit_whatsapp_sticky_btn_icon_size',
			[
				'label' => esc_html__( 'Icon Size (px)', 'elementskit' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 26,
				],
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp .elementskit-whatsapp__popup--btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'whatsapp_header_style_section',
			[
				'label' => esc_html__( 'Header', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'header_bg',
			[
				'label' => esc_html__( 'Header Background', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__header' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'user_photo_headings',
			[
				'label' => esc_html__( 'User Image Style', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'user_img_border',
				'label' => esc_html__( 'Image Border', 'elementskit' ),
				'selector' => '{{WRAPPER}} .elementskit-whatsapp__header--img img',
			]
		);

		$this->add_control(
			'user_info_headings',
			[
				'label' => esc_html__( 'User Info', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'username_color',
			[
				'label' => esc_html__( 'Username Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__header--name' => 'color: {{VALUE}}',
				],
			]
		);
		$this->add_control(
			'user_text_color',
			[
				'label' => esc_html__( 'User Text Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__header--text' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'username_typography',
				'label' => 'Username Typography',
				'selector' => '{{WRAPPER}} .elementskit-whatsapp__header--name',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'user_text_typography',
				'label' => 'User Text Typography',
				'selector' => '{{WRAPPER}} .elementskit-whatsapp__header--text',
			]
		);


		$this->end_controls_section();

		$this->start_controls_section(
			'whatsapp_body_style_section',
			[
				'label' => esc_html__( 'Body', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'asking_text_color',
			[
				'label' => esc_html__( 'Asking Text Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__chat--title' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'asking_text_typography',
				'label' => 'Asking Text Typography',
				'selector' => '{{WRAPPER}} .elementskit-whatsapp__chat--title',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'whatsapp_footer_style_section',
			[
				'label' => esc_html__( 'Footer', 'elementskit' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'input_placeholder_color',
			[
				'label' => esc_html__( 'Input Placeholder Color', 'elementskit' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementskit-whatsapp__input--field::placeholder' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'input_placeholder_typography',
				'label' => 'Input Placeholder Typography',
				'selector' => '{{WRAPPER}} .elementskit-whatsapp__input--field::placeholder',
			]
		);


		$this->end_controls_section();

		$this->insert_pro_message();
	}

	protected function render()
	{
		echo '<div class="ekit-wid-con" >';
		$this->render_raw();
		echo '</div>';
	}

	protected function render_raw()
	{

		$settings = $this->get_settings_for_display();
		$user_img = isset($settings['whatsapp_user_image']['url']) && $settings['whatsapp_user_image']['url'] ? $settings['whatsapp_user_image']['url'] : ElementsKit_Lite::widget_url().'init/assets/img/whatsapp_user.svg';
		$username = $settings['whatsapp_username'];
		$user_text = $settings['whatsapp_user_text'];
		$whatsapp_number = $settings['whatsapp_number'] ?? $settings['whatsapp_number'];
		$asking_text = $settings['whatsapp_asking_text'];
		$input_placeholder = $settings['whatsapp_input_placeholder'];
		$whatsapp_background = ElementsKit_Lite::widget_url().'init/assets/img/bg-whatsapp.png';
		$show_first = $settings['show_from_first'] === 'yes' ? 'show' : 'hide';

?>
		<div class="elementskit-whatsapp">
			<div class="elementskit-whatsapp__content" data-show="<?php esc_attr_e($show_first, 'elementskit'); ?>">
				<div class="elementskit-whatsapp__wrapper" style="background-image : url('<?php echo esc_url($whatsapp_background); ?>">
					<div class="elementskit-whatsapp__header">
						<div class="elementskit-whatsapp__header--img">
							<img src="<?php echo $user_img; ?>" alt="<?php esc_attr_e($username, 'elementskit'); ?>">
						</div>
						<div class="elementskit-whatsapp__header--content">
							<h4 class="elementskit-whatsapp__header--name"><?php _e($username,'elementskit'); ?></h4>
							<p class="elementskit-whatsapp__header--text"><?php _e($user_text,'elementskit'); ?></p>
						</div>
                 	</div>

					<div class="elementskit-whatsapp__body">
						<div class="elementskit-whatsapp__chat">
							<svg viewBox="0 0 8 13" width="8" height="13" class="elementskit-whatsapp__chat--icon-before">
								<path opacity=".13" fill="#FFFFFF" d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"></path>
								<path fill="currentColor" d="M1.533 2.568 8 11.193V0H2.812C1.042 0 .474 1.156 1.533 2.568z"></path>
							</svg>
							<p class="elementskit-whatsapp__chat--title">
								<?php echo sanitize_textarea_field( $asking_text ); ?>
							</p>
						</div>
						<div class="elementskit-whatsapp__typing">
							<svg viewBox="0 0 8 13" width="8" height="13" class="elementskit-whatsapp__typing--wrapper-icon-before">
								<path opacity=".13" d="M5.188 1H0v11.193l6.467-8.625C7.526 2.156 6.958 1 5.188 1z"></path>
								<path fill="currentColor" d="M5.188 0H0v11.193l6.467-8.625C7.526 1.156 6.958 0 5.188 0z"></path>
							</svg>
							<div class="elementskit-whatsapp__typing--wrapper">
								<svg viewBox="0 0 512 512" class="circle-svg">
									<circle cx="256" cy="256" r="48"></circle>
									<circle cx="416" cy="256" r="48"></circle>
									<circle cx="96" cy="256" r="48"></circle>
								</svg>
							</div>
						</div>

					</div>
				</div>

				<div class="elementskit-whatsapp__input">
					<div class="elementskit-whatsapp__input--wrapper">
						<input name="text" type="text" placeholder="<?php esc_attr_e($input_placeholder, 'elementskit'); ?>" class="elementskit-whatsapp__input--field">
						<a type="submit" href="https://api.whatsapp.com/send?phone=<?php esc_attr_e($whatsapp_number, 'elementskit'); ?>&text=" class="elementskit-whatsapp__input--btn">
							<svg viewBox="0 0 32 32">
								<path d="M19.47,31a2,2,0,0,1-1.8-1.09l-4-7.57a1,1,0,0,1,1.77-.93l4,7.57L29,3.06,3,12.49l9.8,5.26,8.32-8.32a1,1,0,0,1,1.42,1.42l-8.85,8.84a1,1,0,0,1-1.17.18L2.09,14.33a2,2,0,0,1,.25-3.72L28.25,1.13a2,2,0,0,1,2.62,2.62L21.39,29.66A2,2,0,0,1,19.61,31Z" />
							</svg>
						</a>
					</div>
				</div>
			</div>
			<div class="elementskit-whatsapp__popup">
				<button class="elementskit-whatsapp__popup--btn">
					<svg xmlns="http://www.w3.org/2000/svg" class="whatsapp-rotate-icon" width="26" height="26" viewBox="0 0 26 26" fill="none">
						<path d="M7.47533 22.3167C9.10033 23.2917 11.0503 23.8334 13.0003 23.8334C18.9587 23.8334 23.8337 18.9584 23.8337 13.0001C23.8337 7.04175 18.9587 2.16675 13.0003 2.16675C7.04199 2.16675 2.16699 7.04175 2.16699 13.0001C2.16699 14.9501 2.70866 16.7917 3.57533 18.4167L2.60744 22.1394C2.41271 22.8883 3.10592 23.5651 3.84998 23.3526L7.47533 22.3167Z" stroke="white" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M17.875 16.0859C17.875 16.2614 17.8359 16.4418 17.7529 16.6173C17.6699 16.7928 17.5625 16.9585 17.4209 17.1145C17.1817 17.3778 16.9181 17.5679 16.6202 17.6898C16.3273 17.8116 16.01 17.875 15.6682 17.875C15.1702 17.875 14.638 17.758 14.0766 17.5191C13.5151 17.2803 12.9536 16.9585 12.397 16.5539C11.8356 16.1444 11.3034 15.691 10.7956 15.1889C10.2928 14.6819 9.8387 14.1505 9.43346 13.5948C9.03311 13.039 8.71088 12.4833 8.47653 11.9324C8.24218 11.3766 8.125 10.8453 8.125 10.3383C8.125 10.0068 8.18359 9.68988 8.30076 9.39738C8.41794 9.1 8.60347 8.827 8.86223 8.58325C9.1747 8.27613 9.51646 8.125 9.87775 8.125C10.0145 8.125 10.1512 8.15425 10.2732 8.21275C10.4002 8.27125 10.5125 8.359 10.6003 8.48575L11.733 10.0799C11.8209 10.2018 11.8844 10.3139 11.9283 10.4211C11.9723 10.5235 11.9967 10.6259 11.9967 10.7185C11.9967 10.8355 11.9625 10.9525 11.8942 11.0646C11.8307 11.1767 11.7379 11.2937 11.6207 11.4107L11.2497 11.7959C11.196 11.8495 11.1716 11.9129 11.1716 11.9909C11.1716 12.0299 11.1765 12.064 11.1862 12.103C11.2009 12.142 11.2155 12.1712 11.2253 12.2005C11.3132 12.3614 11.4645 12.571 11.6793 12.8245C11.899 13.078 12.1334 13.3364 12.3873 13.5948C12.6509 13.8531 12.9048 14.092 13.1636 14.3114C13.4174 14.5259 13.6274 14.6721 13.7934 14.7599C13.8178 14.7696 13.8471 14.7842 13.8813 14.7989C13.9203 14.8135 13.9594 14.8184 14.0033 14.8184C14.0863 14.8184 14.1498 14.7891 14.2035 14.7355L14.5745 14.3699C14.6966 14.248 14.8138 14.1554 14.9261 14.0969C15.0384 14.0286 15.1507 13.9945 15.2727 13.9945C15.3655 13.9945 15.4631 14.014 15.5705 14.0579C15.678 14.1018 15.7902 14.1651 15.9123 14.248L17.5284 15.3936C17.6553 15.4814 17.7432 15.5837 17.7969 15.7056C17.8457 15.8275 17.875 15.9494 17.875 16.0859Z" stroke="white" stroke-width="1.5" stroke-miterlimit="10" />
					</svg>
				</button>
			</div>
		</div>
<?php
	}
}
