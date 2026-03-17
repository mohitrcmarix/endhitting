<?php

namespace WPPayForm\App\Modules\Pro\Classes\Coupons;

use WPPayForm\App\Models\Form;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Coupon
{
    private $table = 'wppayform_coupons';

    public function getCoupons()
    {
        $paginate = $_REQUEST['pagination'];
        $status = get_option('wppayform_coupon_status');

        if ($status != 'yes') {
            wp_send_json([
                'coupon_status' => false
            ], 200);
        }

        $couponModel = new CouponModel();
        $coupons = $couponModel->getCoupons($paginate);

        $data = [
            'coupon_status' => 'yes',
            'coupons' => $coupons['coupons'],
            'total' => $coupons['total']
        ];

        $forms = Form::select(array('ID', 'post_title'))
            ->where('post_type', 'wp_payform')
            ->orderBy('ID', 'DESC')
            ->get();
        $formattedForms = [];

        foreach ($forms as $form) {
            $formattedForms[$form->ID] = $form->post_title;
        }
        $data['available_forms'] = $formattedForms;
        return $data;
    }

    public function enableCoupons()
    {
        (new CouponModel())->migrate();
        update_option('wppayform_coupon_status', 'yes', 'no');
        return [
            'coupon_status' => 'yes'
        ];
    }

    public function saveCoupon($request)
    {
        $coupon = wp_unslash($request->coupon);
        $required = [
            'title' => 'required',
            'code' => 'required',
            'amount' => 'required',
            'coupon_type' => 'required',
            'status' => 'required'
        ];

        foreach ($required as $key => $value) {
            if (empty($coupon[$key])) {
                wp_send_json([
                    'errors' => '',
                    'message' => "Please fill up $key field"
                ], 423);
            }
        }

        $couponId = false;

        if (isset($coupon['id'])) {
            $couponId = $coupon['id'];
            unset($coupon['id']);
        }

        if ($exist = (new CouponModel())->isCouponCodeAvailable($coupon['code'], $couponId)) {
            wp_send_json([
                'errors' => [
                    'code' => [
                        'exist' => 'Same coupon code is already exists'
                    ]
                ],
                'message' => 'Same coupon code is already exists'
            ], 423);
        }

        if ($couponId) {
            (new CouponModel())->updateCoupon($couponId, $coupon);
        } else {
            $couponId = (new CouponModel())->insertCoupon($coupon);
        }

        return [
            'message' => __('Coupon has been created successfully', 'wppayform'),
            'coupon_id' => $couponId
        ];
    }

    public function deleteCoupon($request)
    {
        $couponId = intval($request->coupon_id);
        (new CouponModel())->deleteCoupon($couponId);
        return [
            'message' => __('Coupon has been successfully deleted', 'wppayform'),
            'coupon_id' => $couponId
        ];
    }
}
