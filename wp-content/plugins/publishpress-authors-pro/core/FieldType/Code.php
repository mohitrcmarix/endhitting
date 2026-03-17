<?php

namespace PPAuthorsPro\FieldType;

class Code
{
    public static function addHooks()
    {
        add_action('cmb2_render_code', [__CLASS__, 'render']);
        add_filter('cmb2_sanitize_code', [__CLASS__, 'sanitize'], 10, 2);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminScripts']);
    }

    /**
     * @param $overrideValue
     * @param $value
     *
     * @return mixed
     */
    public static function sanitize($overrideValue, $value)
    {
        // Strip script and iframe tags.
        $value = preg_replace('#<(script|iframe)[^>]*>[^<]*<\/(script|iframe)[^>]*>#', '', $value);

        // Strip script from attributes
        $value = preg_replace('#on[a-z]+=\s*[\'"]?.*[\'"]?(.*>)#i', '$1', $value);

        $overrideValue = $value;

        return $value;
    }

    /**
     * @param array $field
     */
    public static function render($field = [])
    {
        $editorId = $field->args['id'] . '_editor';

        $layoutCode = $field->value;
        if (empty($layoutCode)) {
            $layoutCode = file_get_contents(PP_AUTHORS_PRO_BASE_PATH . 'twig/author_layout/default.twig');
        }
        ?>
        <textarea
                id="<?php echo esc_attr($field->args['id']); ?>"
                name="<?php echo esc_attr($field->args['id']); ?>"
                class="publishpress-authors-fallback-code"
        ><?php echo esc_html($field->value); ?></textarea>
        <div class="publishpress-authors-editor-wrapper">
            <div
                    id="<?php
                    echo esc_attr($editorId); ?>"
                    name="<?php
                    echo esc_attr($editorId); ?>"
                    data-code="<?php
                    echo esc_attr(base64_encode($layoutCode)); ?>"
                    data-textarea-selector="#<?php
                    echo esc_attr($field->args['id']); ?>"
                    class="publishpress-authors-layout-editor"></div>
        </div>
        <?php
        if (!empty($field->args['desc'])) {
            ?>
            <p class="cmb2-metabox-description"><?php echo esc_html($field->args['desc']); ?></p>
            <?php
        }
    }

    public static function enqueueAdminScripts()
    {
        global $pagenow, $post_type;

        if (
            ! in_array($pagenow, ['post.php', 'post-new.php'])
            || $post_type !== 'ppmacf_layout'
        ) {
            return;
        }

        $moduleAssetsUrl = PP_AUTHORS_PRO_URL . '/modules/author-custom-layouts/assets';

        wp_enqueue_script(
            'publishpress-authors-ace',
            $moduleAssetsUrl . '/js/ace/ace.js',
            [
                'jquery',
            ],
            PP_AUTHORS_PRO_VERSION
        );

        wp_enqueue_script(
            'publishpress-authors-ace-twig',
            $moduleAssetsUrl . '/js/ace/mode-twig.js',
            [
                'jquery',
                'publishpress-authors-ace',
            ],
            PP_AUTHORS_PRO_VERSION
        );

        wp_enqueue_script(
            'publishpress-authors-layout-screen',
            $moduleAssetsUrl . '/js/layout-screen.js',
            [
                'jquery',
                'publishpress-authors-ace',
                'publishpress-authors-ace-twig',
            ],
            PP_AUTHORS_PRO_VERSION
        );

        wp_enqueue_style(
            'publishpress-authors-layout-screen-style',
            $moduleAssetsUrl . '/css/layout-screen.css',
            [],
            PP_AUTHORS_PRO_VERSION
        );
    }
}
