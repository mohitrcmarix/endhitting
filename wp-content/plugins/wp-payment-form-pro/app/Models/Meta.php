<?php

namespace WPPayForm\App\Models;

class Meta extends Model
{
    public $table = 'wpf_meta';

    public function updateOrderMeta($metaGroup, $optionId, $key, $value, $formId = null)
    {
        $value = maybe_serialize($value);
        $exists = $this->where('meta_group', $metaGroup)
            ->where('meta_key', $key)
            ->where('option_id', $optionId)
            ->first();

        if ($exists) {
            $this->where('id', $exists->id)
                ->update([
                    'meta_group' => $metaGroup,
                    'option_id' => $optionId,
                    'meta_key' => $key,
                    'meta_value' => $value,
                    'form_id' => $formId,
                    'updated_at' => current_time('mysql')
                ]);
            return $exists->id;
        }

        return $this->insert([
            'meta_group' => $metaGroup,
            'option_id' => $optionId,
            'meta_key' => $key,
            'meta_value' => $value,
            'form_id' => $formId,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
    }

    public static function migrate()
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'wpf_meta';
        $sql = "ALTER TABLE $tableName
            ADD form_id int(11)";
        $upgrade = $wpdb->query($sql);
        return $upgrade;
    }
}
