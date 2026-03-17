<?php

namespace WPPayForm\App\Modules\Pro\Classes\Coupons;

use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CouponController
{
    public function test()
    {
        return 'testing';
    }

    public function validateCoupon()
    {
        $code = sanitize_text_field($_REQUEST['coupon']);
        $formId = intval($_REQUEST['form_id']);
        $totalAmount = intval($_REQUEST['payment_total']);

        $couponModel = new CouponModel();
        $coupon = $couponModel->getCouponByCode($code);

        $startDate = strtotime($coupon->start_date);
        $endDate = strtotime($coupon->expire_date);
        $currentTime = date('Y-m-d');

        $dateTime = current_datetime();
        $localtime = $dateTime->getTimestamp() + $dateTime->getOffset();

        if ($coupon->min_amount && $totalAmount < intval($coupon->min_amount * 100)) {
            wp_send_json([
                'message' => __('The provided coupon is not applicable with this amount', 'wppayform')
            ], 423);
        }

        if ($startDate && $localtime <= $startDate) {
            wp_send_json([
                'message' => __('The provided coupon is not live yet', 'wppayform')
            ], 423);
        }

        if ($endDate && $localtime > $endDate) {
            wp_send_json([
                'message' => __('The provided coupon is outdated', 'wppayform')
            ], 423);
        }

        if (!$coupon || $coupon->status != 'active') {
            wp_send_json([
                'message' => __('The provided coupon is not valid', 'wppayform')
            ], 423);
        }

        if ($formIds = Arr::get($coupon->settings, 'allowed_form_ids')) {
            if (!in_array($formId, $formIds)) {
                wp_send_json([
                    'message' => __('The provided coupon is not valid', 'wppayform')
                ], 423);
            }
        }

        $formIds = Arr::get($coupon->settings, 'allowed_form_ids');

        if ($coupon->min_amount && $coupon->min_amount > $totalAmount) {
            wp_send_json([
                'message' => __('The provided coupon does not meet the requirements', 'wppayform')
            ], 423);
        }

        $otherCouponCodes = Arr::get($_REQUEST, 'other_coupons', '');
        if ($otherCouponCodes) {
            $otherCouponCodes = \json_decode(wp_unslash($otherCouponCodes), false);

            if ($otherCouponCodes) {
                $codes = $couponModel->getCouponsByCodes($otherCouponCodes);
                foreach ($codes as $couponItem) {
                    if (($couponItem->stackable != 'yes' || $coupon->stackable != 'yes') && $coupon->code != $couponItem->code) {
                        wp_send_json([
                            'message' => __('Sorry, You can not apply this coupon with other coupon code', 'wppayform')
                        ], 423);
                    }
                }
            }
        }

        wp_send_json([
            'coupon' => [
                'code' => $coupon->code,
                'title' => $coupon->title,
                'amount' => $coupon->amount,
                'coupon_type' => $coupon->coupon_type
            ]
        ], 200);
    }

    public function getTotalLine($coupons, $paymentTotal, $taxTotal = 0)
    {
        $totalDiscounts = 0;
        $discounts = array();

        if ($taxTotal) {
            $paymentTotal -= $taxTotal;
        }

        foreach ($coupons as $coupon) {
            if ($coupon->coupon_type == 'percent') {
                $price = (intval($coupon->amount) / 100) * intval($paymentTotal);
            } else {
                $price = intval($coupon->amount) * 100;
            }
            $totalDiscounts += $price;
            $item = [
                "type" => "discount",
                "parent_holder" => "payment_item",
                "item_name" => $coupon->title,
                "quantity" => 1,
                "created_at" => $coupon->created_at,
                "updated_at" => $coupon->updated_at,
                "item_price" => $price,
                "line_total" => $price,
            ];

            $discounts[] = $item;
        }
        return array(
            'discounts' => $discounts,
            'totalDiscounts' => $totalDiscounts
        );
    }
}
