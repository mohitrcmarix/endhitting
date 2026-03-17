<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\File\FileHandler;
use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Form;

if (!defined('ABSPATH')) {
    exit;
}

class FileUploadComponent extends BaseComponent
{
    protected $componentName = 'file_upload_input';

    public function __construct()
    {
        parent::__construct($this->componentName, 12);
        add_action('wp_ajax_wpf_file_upload_process', array($this, 'handleFileUpload'));
        add_action('wp_ajax_nopriv_wpf_file_upload_process', array($this, 'handleFileUpload'));
        add_filter('wppayform/submitted_value_' . $this->componentName, array($this, 'formatUploadedValue'), 10, 3);
        add_filter('wppayform/validate_data_on_submission_' . $this->componentName, array($this, 'validateUploadedValue'), 10, 4);
        add_filter('wppayform/rendering_entry_value_' . $this->componentName, array($this, 'convertValueToHtml'), 10, 3);
        add_action('wppayform/require_entry_html', array($this, 'registerConvertHtml'));
        add_filter('wppayform/require_entry_html_done', array($this, 'deRegisterConvertHtml'));
    }

    public function component()
    {
        return array(
            'type' => $this->componentName,
            'editor_title' => __('File Upload', 'wppayform'),
            'group' => 'input',
            'postion_group' => 'general',
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Upload Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'button_text' => array(
                    'label' => 'Upload Button Text',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'required' => array(
                    'label' => 'Required',
                    'type' => 'switch',
                    'group' => 'general'
                ),
                'max_file_size' => array(
                    'label' => 'Max File Size (in MegaByte)',
                    'type' => 'number',
                    'group' => 'general'
                ),
                'max_allowed_files' => array(
                    'label' => 'Max Upload Files',
                    'type' => 'number',
                    'group' => 'general'
                ),
                'allowed_files' => array(
                    'label' => 'Allowed file types',
                    'type' => 'checkbox',
                    'wrapper_class' => 'checkbox_new_lined',
                    'options' => $this->getFileTypes('label')
                ),
                'admin_label' => array(
                    'label' => 'Admin Label',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
                'wrapper_class' => array(
                    'label' => 'Field Wrapper CSS Class',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
                'element_class' => array(
                    'label' => 'Input element CSS Class',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
            ),
            'field_options' => array(
                'label' => 'Upload Your File',
                'button_text' => 'Drag & Drop your files or Browse',
                'required' => 'yes',
                'max_file_size' => 2,
                'max_allowed_files' => 1,
                'allowed_files' => ['images'],
            )
        );
    }

    public function render($element, $form, $elements)
    {
        wp_enqueue_script('wppayform_file_upload');

        add_filter('wppayform/form_css_classes', function ($classes, $reneringForm) use ($form) {
            if ($reneringForm->ID == $form->ID) {
                $classes[] = 'wpf_form_has_file_upload';
            }
            return $classes;
        }, 10, 2);

        $fieldOptions = Arr::get($element, 'field_options');
        $disable = Arr::get($fieldOptions, 'disable');
        $controlClass = $this->elementControlClass($element);
        $inputId = 'wpf_input_' . $form->ID . '_' . $element['id'];
        $element['extra_input_class'] = 'wpf_file_upload_element';

        if ($disable) {
            return;
        }

        $inputClass = $this->elementInputClass($element);

        $maxFileSize = Arr::get($fieldOptions, 'max_file_size');

        $accepts = implode(',', $this->getFileAcceptExtensions($element));

        $maxFilesCount = Arr::get($fieldOptions, 'max_allowed_files');

        $btnText = Arr::get($fieldOptions, 'button_text');
        if (!$btnText) {
            $btnText = 'Drag & Drop your files or Browse';
        }


        $associateKey = '__' . $element['id'] . '_files';
        $attributes = array(
            'data-target_name' => $element['id'],
            'value' => '',
            'type' => 'file',
            'accept' => $accepts,
            'data-max_files' => $maxFilesCount,
            'data-max_file_size' => $maxFileSize,
            'data-associate_key' => $associateKey,
            'data-btn_txt' => htmlspecialchars($btnText),
            'class' => $inputClass,
            'id' => $inputId,
            'multiple' => 'true'
        );
        if ($maxFilesCount > 1) {
            $attributes['multiple'] = 'true';
        }

        $hiddenAttributes = [
            'type' => 'hidden',
            'name' => $element['id'],
            'value' => $associateKey,
            'data-required' => Arr::get($fieldOptions, 'required'),
            'data-type' => 'file_upload'
        ];

        ?>
        <div data-element_type="<?php echo $this->elementName; ?>"
             class="<?php echo $controlClass; ?>">
            <?php $this->buildLabel($fieldOptions, $form, array('for' => $inputId)); ?>
            <div class="wpf_input_content wpf_file_upload_wrapper dropzone dropzone_parent">
                <input <?php echo $this->builtAttributes($hiddenAttributes); ?> />
                <input <?php echo $this->builtAttributes($attributes); ?> />
            </div>
            <div class="upload_error_message"></div>
        </div>
        <?php
    }

    public function validateUploadedValue($error, $elementId, $element, $form_data)
    {
        // Check it's required
        $isRequired = Arr::get($element, 'options.required') == 'yes';

        if (!$isRequired) {
            return false;
        }

        $dataName = Arr::get($form_data, $elementId);
        $dataValues = Arr::get($form_data, $dataName);

        if (!$dataValues) {
            $error = Arr::get($element, 'options.label') . ' is required, Please upload required files';
        }
        return $error;
    }

    public function formatUploadedValue($dataName, $element, $data)
    {
        if (!$dataName) {
            return array();
        }
        $files = Arr::get($data, $dataName, []);
        $fullPathFiles = array();
        $uploadDir = get_option('wppayform_upload_dir');
        foreach ($files as $file) {
            $fullPathFiles[] = $uploadDir . '/' . $file;
        }
        return $fullPathFiles;
    }

    public function registerConvertHtml()
    {
        add_filter('wppayform/maybe_conver_html_' . $this->componentName, array($this, 'convertValueToHtml'), 10, 3);
    }

    public function deRegisterConvertHtml()
    {
        remove_filter('wppayform/maybe_conver_html_' . $this->componentName, array($this, 'convertValueToHtml'), 10, 3);
    }

    public function convertValueToHtml($values, $submission, $element)
    {
        if (empty($values)) {
            return '';
        }
        $html = '<div class="payform_file_lists">';
        foreach ($values as $file) {
            $previewUrl = $this->getPreviewUrl($file);
            $html .= '<div class="payform_each_file"><a title="Click to View/Download" href="' . $file . '" target="_blank" rel="noopener"><img src="' . $previewUrl . '" /></a></div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function getFileAcceptExtensions($element)
    {
        $fieldOptions = Arr::get($element, 'field_options');
        $allowedFiles = Arr::get($fieldOptions, 'allowed_files');
        $fileTypes = $this->getFileTypes('accepts');
        $accepts = [];


        foreach ($allowedFiles as $allowedFile) {
            $accepts[] = Arr::get($fileTypes, $allowedFile);
        }
        $accepts = array_filter($accepts);
        $accepts = implode(',', $accepts);
        $accepts = explode(',', $accepts);
        return array_map('trim', $accepts);

    }

    private function getFileTypes($pairType = false)
    {
        $types = array(
            'images' => array(
                'label' => 'Images (jpg, jpeg, gif, png, bmp)',
                'accepts' => '.jpg,.jpeg,.gif,.png,.bmp'
            ),
            'audios' => array(
                'label' => 'Audio (mp3, wav, ogg, wma, mka, m4a, ra, mid, midi)',
                'accepts' => '.mp3, .wav, .ogg, .wma, .mka, .m4a, .ra, .mid, .midi, .mpga'
            ),
            'pdf' => array(
                'label' => 'pdf',
                'accepts' => '.pdf'
            ),
            'docs' => array(
                'label' => 'Docs (doc, ppt, pps, xls, mdb, docx, xlsx, pptx, odt, odp, ods, odg, odc, odb, odf, rtf, txt)',
                'accepts' => '.doc,.ppt,.pps,.xls,.mdb,.docx,.xlsx,.pptx,.odt,.odp,.ods,.odg,.odc,.odb,.odf,.rtf,.txt'
            ),
            'zips' => array(
                'label' => 'Zip Archives (zip, gz, gzip, rar, 7z)',
                'accepts' => '.zip,.gz,.gzip,.rar,.7z'
            ),
            'csv' => array(
                'label' => 'CSV (csv)',
                'accepts' => '.csv'
            )
        );

        $types = apply_filters('wppayform/upload_files_available', $types);

        if ($pairType) {
            $pairs = [];
            foreach ($types as $typeName => $type) {
                $pairs[$typeName] = Arr::get($type, $pairType);
            }
            return $pairs;
        }

        return $types;
    }

    public function handleFileUpload()
    {
        $formId = Arr::get($_REQUEST, 'form_id');
        $elementName = Arr::get($_REQUEST, 'element_name');

        if (!$formId || !$elementName) {
            $this->sendError('Wrong file upload instance', 'no element found');
        }

        $element = $this->getFileUploadElement($formId, $elementName);
        $fieldOptions = Arr::get($element, 'field_options');
        if (!$element || !$fieldOptions) {
            $this->sendError('Element not found', 'no element found');
        }

        $uploadFile = $_FILES['file'];
        // We have to validate the uploaded file now
        $file = $this->handleUploadFile($uploadFile, $element, $formId);

        if (!empty($file['error'])) {
            $this->sendError($file['error'], $file);
        }

        $file['original_name'] = sanitize_text_field($uploadFile['name']);
        update_option('wppayform_upload_dir', dirname($file['url']));

        wp_send_json($file, 200);
    }

    public function getFileUploadElement($formId, $elementName)
    {
        $allEmenets = Form::getBuilderSettings($formId);
        foreach ($allEmenets as $element) {
            if ($element['id'] == $elementName && $element['type'] == $this->componentName) {
                return $element;
            }
        }

        return array();
    }

    private function handleUploadFile($file, $element, $formId)
    {
        $fileHandler = new FileHandler($file);
        $fileHandler->overrideUploadDir();


        $errors = $fileHandler->validate([
            'extensions' => $this->getFileAcceptExtensions($element),
            'max_file_size' => Arr::get($element, 'field_options.max_file_size')
        ]);

        $errors = apply_filters('wppayform/upload_validation_errors', $errors, $file, $element, $formId);

        if ($errors) {
            $errorMessage = 'Validation Failed';
            if (is_array($errors)) {
                $errorMessage .= '<ul>';
                foreach ($errors as $error) {
                    $errorMessage .= '<li>' . $error . '</li>';
                }
                $errorMessage .= '</ul>';
            }
            $this->sendError($errorMessage, $errors);
        }
        return $fileHandler->upload();
    }

    private function sendError($message, $error = false, $code = 423)
    {
        wp_send_json_error(array(
            'message' => $message,
            'error' => $error,
            'ok' => 'ine'
        ), 423);
    }

    private function getPreviewUrl($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $imageExtensions = array('jpg', 'jpeg', 'gif', 'png', 'bmp');
        if (in_array($ext, $imageExtensions)) {
            return $file;
        }
        // Return normal Document Extension
        return WPPAYFORM_URL . '/assets/images/document.png';
    }
}