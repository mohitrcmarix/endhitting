<?php

namespace WPPayForm\App\Modules\Pro\Classes\Coupons;

use WPPayForm\App\Models\Model;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CouponModel extends Model
{
    public $table = 'wpf_coupons';

    public function getCoupons($paginate = false)
    {
        $query = $this;
        if ($paginate) {
            $perPage = intval($paginate['per_page']);
            $offset = intval(($paginate['current_page']-1) * $perPage);
            $coupons = $query->offset($offset)
            ->limit($perPage)
            ->get();

            foreach ($coupons as $coupon) {
                $coupon->settings = maybe_unserialize($coupon->settings);
                if ($coupon->start_date == '0000-00-00') {
                    $coupon->start_date = '';
                }
                if ($coupon->expire_date == '0000-00-00') {
                    $coupon->expire_date = '';
                }
            }
        } else {
            $coupons = $query->get();
            foreach ($coupons as $coupon) {
                $coupon->settings = maybe_unserialize($coupon->settings);
                if ($coupon->start_date == '0000-00-00') {
                    $coupon->start_date = '';
                }
                if ($coupon->expire_date == '0000-00-00') {
                    $coupon->expire_date = '';
                }
            }
        }

        return array (
            'coupons' => $coupons,
            'total' => $query->count()
        );
    }

    public function getCouponByCode($code)
    {
        $coupon = self::where('code', $code)
            ->first();

        if (!$coupon) {
            return $coupon;
        }

        $coupon->settings = maybe_unserialize($coupon->settings);

        if ($coupon->start_date == '0000-00-00') {
            $coupon->start_date = '';
        }

        if ($coupon->expire_date == '0000-00-00') {
            $coupon->expire_date = '';
        }
        return $coupon;
    }

    public function getCouponsByCodes($codes)
    {
        $coupons = self::whereIn('code', $codes)
            ->get();
        foreach ($coupons as $coupon) {
            $coupon->settings = maybe_unserialize($coupon->settings);
            if ($coupon->start_date == '0000-00-00') {
                $coupon->start_date = '';
            }
            if ($coupon->expire_date == '0000-00-00') {
                $coupon->expire_date = '';
            }
        }

        return $coupons;
    }

    public function insertCoupon($data)
    {
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();

        $data['settings'] = maybe_serialize($data['settings']);

        return self::insert($data);
    }

    public function updateCoupon($id, $data)
    {
        $data['updated_at'] = current_time('mysql');
        if (isset($data['settings'])) {
            $data['settings'] = maybe_serialize($data['settings']);
        } else {
            $data['settings'] = maybe_serialize([]);
        }

        return self::where('id', $id)
            ->update($data);
    }

    public function deleteCoupon($id)
    {
        return self::where('id', $id)
            ->delete();
    }

    public function getValidCoupons($coupons, $formId, $amountTotal, $taxTotal = 0)
    {
        if ($taxTotal) {
            $amountTotal = $amountTotal - $taxTotal;
        }
        $amountTotal = $amountTotal / 100; // convert cents to money
        $validCoupons = [];

        $otherCouponCodes = [];
        foreach ($coupons as $coupon) {
            if ($coupon->status != 'active') {
                continue;
            }

            if ($formIds = Arr::get($coupon->settings, 'allowed_form_ids')) {
                if (!in_array($formId, $formIds)) {
                    continue;
                }
            }

            if ($coupon->min_amount && $coupon->min_amount > $amountTotal) {
                continue;
            }

            if ($otherCouponCodes && $coupon->stackable != 'yes') {
                continue;
            }

            $discountAmount = $coupon->amount;
            if ($coupon->coupon_type == 'percent') {
                $discountAmount = ($coupon->amount / 100) * $amountTotal;
            }

            $amountTotal = ($amountTotal - $discountAmount) + $taxTotal;
            $otherCouponCodes[] = $coupon->code;

            $validCoupons[] = $coupon;
        }
        return $validCoupons;
    }

    public function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . $this->table;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
				id int(11) NOT NULL AUTO_INCREMENT,
				title varchar(192),
				code varchar(192),
				coupon_type varchar(255) DEFAULT 'percent',
				amount decimal(10,2) NULL,
				status varchar(192) DEFAULT 'active',
				stackable varchar(192) DEFAULT 'no',
				settings longtext,
				created_by INT(11) NULL,
				min_amount INT(11) NULL,
				max_use INT(11) NULL,
				start_date date NULL,
				expire_date date NULL,
				created_at timestamp NULL,
				updated_at timestamp NULL,
				PRIMARY  KEY  (id)
			  ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        return;
    }

    public function isCouponCodeAvailable($code, $exceptId = false)
    {
        $query = self::where('code', $code);
        if ($exceptId) {
            $query = $query->where('id', '!=', $exceptId);
        }
        return $query->first();
    }
}
