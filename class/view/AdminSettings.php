<?php
namespace nomination\view;

use \nomination\Context;
use \nomination\UserStatus;

  /**
   * AdminSettings
   *
   * View for administrative settings
   *
   * @author Daniel West <dwest at tux dot appstate dot edu>
   * @author Robert Bost <bostrt at tux dot appstate dot edu>
   */

class AdminSettings extends \nomination\View {

    public function getRequestVars()
    {
        return array('view' => 'AdminSettings');
    }

    public function display(Context $context)
    {
        if(!UserStatus::isAdmin()){
            throw new \nomination\exception\PermissionException('You are not allowed to see this!');
        }
        $tpl = array();

        // Create factories
        $cmdFactory = new \nomination\CommandFactory();


        // Initialize form submit command
        $updateCmd = $cmdFactory->get('UpdateSettings');
        $form = new \PHPWS_Form('admin_settings');
        $updateCmd->initForm($form);

        // Award title
        $form->addText('award_title', \PHPWS_Settings::get('nomination', 'award_title'));
        $form->setLabel('award_title', 'Award Title:');
        $form->setSize('award_title', 30);
        $form->addCssClass('award_title', 'form-control');

        // Number of references required
        $numRefs = \PHPWS_Settings::get('nomination', 'num_references_req');
        $form->addText('num_references_req', isset($numRefs)?$numRefs:1);  // Default to 1 required reference
        $form->setLabel('num_references_req', '# References Required');
        $form->setSize('num_references_req', 3);
        $form->setMaxSize('num_references_req', 1);
        $form->addCssClass('num_references_req', 'form-control');

        // File storage path
        $form->addText('file_dir', \PHPWS_Settings::get('nomination', 'file_dir'));
        $form->setLabel('file_dir', 'File Directory:');
        $form->setSize('file_dir', 30);
        $form->addCssClass('file_dir', 'form-control');

        // Allowed file types
        $types = \nomination\NominationDocument::getFileNames();
        $enabled = unserialize(\PHPWS_Settings::get('nomination', 'allowed_file_types'));
        $form->addCheckAssoc('allowed_file_types', $types);
        $form->setMatch('allowed_file_types', $enabled);
        $form->useRowRepeat();

        // Email from address
        $form->addText('email_from_address', \PHPWS_Settings::get('nomination', 'email_from_address'));
        $form->setLabel('email_from_address', 'Email From Address');
        $form->setSize('email_from_address', 30);
        $form->addCssClass('email_from_address', 'form-control');

        // Signature
        $form->addText('signature_title', \PHPWS_Settings::get('nomination', 'signature'));
        $form->setLabel('signature_title', 'Signature Title');
        $form->setSize('signature_title', 30);
        $form->addCssClass('signature_title', 'form-control');

        // Signature Position
        $form->addText('sig_position', \PHPWS_Settings::get('nomination', 'sig_position'));
        $form->setLabel('sig_position', 'Signature Position');
        $form->setSize('sig_position', 30);
        $form->addCssClass('sig_position', 'form-control');

        // Hidden Fields
        \PHPWS_Core::initModClass('nomination', 'NominationFieldVisibility.php');
        $vis = new \nomination\NominationFieldVisibility();
        $vis->prepareSettingsForm($form, 'show_fields');

        $form->addSubmit('Update');

        $form->useRowRepeat();
        $form->mergeTemplate($tpl);
        $tpl = $form->getTemplate();

        \Layout::addPageTitle('Admin Settings');

        return \PHPWS_Template::process($tpl, 'nomination', 'admin/settings.tpl');
    }
}
