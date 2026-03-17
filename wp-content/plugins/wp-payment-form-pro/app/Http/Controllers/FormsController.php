<?php


namespace WPPayForm\App\Http\Controllers;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\GlobalTools;
use WPPayForm\Framework\Request\Request;

class FormsController extends Controller
{
    public function index()
    {
        $perPage = absint($this->request->per_page);
        $pageNumber = absint($this->request->page_number);
        $searchString = sanitize_text_field($this->request->search_string);

        $args = array(
            'posts_per_page' => $perPage,
            'offset' => $perPage * ($pageNumber - 1)
        );

        $args = apply_filters('wppayform/get_all_forms_args', $args);

        if ($searchString) {
            $args['s'] = $searchString;
        }

        $forms = Form::getForms($this->request, $args, $with = array('entries_count'));

        return $forms;
    }

    public function store()
    {
        $postTitle = $this->request->get('post_title');
        if (!$postTitle) {
            $postTitle = 'Blank Form';
            return;
        }
        $template = $this->request->get('template');

        $data = array(
            'post_title' => $postTitle,
            'post_status' => 'publish'
        );

        do_action('wppayform/before_create_form', $data, $template);

        $formId = Form::store($data);

        wp_update_post([
            'ID' => $formId,
            'post_title' => $data['post_title'] . ' (#' . $formId . ')'
        ]);

        if (is_wp_error($formId)) {
            wp_send_json_error(array(
                'message' => __('Something is wrong when creating the form. Please try again', 'wppayform')
            ), 423);
            return;
        }

        do_action('wppayform/after_create_form', $formId, $data, $template);

        wp_send_json_success(array(
            'message' => __('Form successfully created.', 'wppayform'),
            'form_id' => $formId
        ), 200);
    }

    public function demo()
    {
        do_action('wppayform/doing_ajax_forms_get_forms');

        $forms = Form::demoForms();
        $formattedForms = [];
        foreach ($forms as $formName => $form) {
            unset($form['data']);
            $formattedForms[$formName] = $form;
        }
        wp_send_json_success(array(
            'demo_forms' => $formattedForms
        ), 200);
    }

    public function formatted()
    {
        return array(
            'available_forms' => Form::getAllAvailableForms()
        );
    }

    public function import()
    {
        $globalTools = new GlobalTools();
        $globalTools->handleImportForm($this->request);
    }
}
