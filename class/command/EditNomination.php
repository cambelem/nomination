<?php
namespace nomination\command;

use \nomination\Command;
use \nomination\Context;
use \nomination\Nomination;
use \nomination\NominationFactory;
use \nomination\view\NominationForm;
use \nomination\NominationDocument;
use \nomination\DocumentFactory;
use \nomination\Reference;
use \nomination\ReferenceFactory;
use \nomination\NominationEmail;
use \nomination\Period;
use \nomination\NominationFieldVisibility;
use \nomination\ReferenceEmail;
use \nomination\NominatorEmail;
use \nomination\view\NotificationView;
/**
 * EditNomination
 *
 * Check that all required fields in form are completed, if not
 * redirect back to form with neglected fields highlighted and
 * Notification at top of form.
 * If form checks out then save the new nomination over the old
 *
 * @author Chris Detsch
 */

\PHPWS_Core::initModClass('nomination', 'Command.php');
\PHPWS_Core::initModClass('nomination', 'Context.php');
\PHPWS_Core::initModClass('nomination', 'NominationDocument.php');
\PHPWS_Core::initModClass('nomination', 'view/NominationForm.php');

class EditNomination extends Command
{
    public $unique_id;

    public static $requiredFields = array(
                    'nominee_banner_id',
                    'nominee_first_name',
				    'nominee_last_name',
				    'nominee_email',
                    'nominee_phone',
                    'nominee_gpa',
                    'nominee_asubox',
                    'nominee_class',
				    'nominator_first_name',
				    'nominator_last_name',
				    'nominator_email',
				    'nominator_phone',
				    'nominator_address',
            'reference_first_name',
            'reference_last_name',
            'reference_phone',
            'reference_email');

    public function getRequestVars()
    {
        $vars = array('action' => 'EditNomination', 'after' => 'ThankYouNominator');

        if(isset($this->unique_id)){
            $vars['unique_id'] = $this->unique_id;
        }

        return $vars;
    }

    public function execute(Context $context)
    {
      $missing = array();
      $entered = array();
      $numReferencesReq = Reference::getNumReferencesReq();
      $nomination = NominationFactory::getByNominatorUniqueId($context['unique_id']);

      // Figure out which fields are required
      \PHPWS_Core::initModClass('nomination', 'NominationFieldVisibility.php');
      $vis = new NominationFieldVisibility();

      $required = array();

      foreach(self::$requiredFields as $field) {
          if($vis->isVisible($field))
          {
            switch($field){
              case "reference_first_name":
              case "reference_last_name":
              case "reference_phone":
              case "reference_email":
                for($i = 0; $i < $numReferencesReq; $i++)
                {
                  array_push($required, $field.'_'.$i);
                }
                break;
              default:
                $required[] = $field;
            }
          }
      }



      /*****************
       * Check  fields *
      *****************/

      // Check for missing required fields
      /*
      // TODO: Fix this so that it doesn't complain about fields that the user can't fill in.
      foreach($required as $key=>$value){
          if(!isset($context[$value]) || $context[$value] == ""){
              $missing[] = $value;
          } else {
              $entered[$key] = $context[$value];
          }
      }*/


      // If anything was missing, redirect back to the form
      // TODO: Fix this so that it shows a useful error notification if the user gets the CAPTCHA wrong
      if(!empty($missing) || !Captcha::verify()){
          // Notify the user that they must reselect their file

          $context['after'] = 'NominationForm';// Set after view to the form
          $context['missing'] = $missing;// Add missing fields to context
          $context['form_fail'] = True;// Set form fail
          // Throw exception
          $missingFields = implode(', ', $missing);
          // TODO
          //throw new \nomination\exception\BadFormException('The following fields are missing: ' . $missingFields);
      }

      $oldNomination = NominationFactory::getByNominatorUniqueId($context['nominator_unique_id']);

      $doc = new DocumentFactory();
      $doc = $doc->getDocumentById($nomination->getId());
      // Needed so that the document save function will update the file if needed.
      $docId = $doc->getId();
      if($doc == null || $_FILES['statement']['size'] > 0 || is_uploaded_file($_FILES['statement']['tmp_name'])){
          // Sanity check on mime type for files the client may still have open
          if($_FILES['statement']['type'] == 'application/octet-stream')
          {
              throw new \nomination\exception\IllegalFileException('Please save and close all word processors then re-submit file.');
          }

          $doc = new NominationDocument($nomination, 'nominator', 'statement', $_FILES['statement']);
          $doc->setId($docId);
          DocumentFactory::save($doc);
      }

      /***********
       * Nominee *
       ***********/
      $nomineeBannerId    = $context['nominee_banner_id'];
      $nomineeFirstName   = $context['nominee_first_name'];
      $nomineeMiddleName  = $context['nominee_middle_name'];
      $nomineeLastName    = $context['nominee_last_name'];
      $nomineeAsubox      = $context['nominee_asubox'];
      $nomineeEmail       = $context['nominee_email'] . '@appstate.edu';
      $nomineePosition    = $context['nominee_position'];
      $nomineeDeptMajor   = $context['nominee_department_major'];
      $nomineeYears       = $context['nominee_years'];
      $nomineePhone       = $context['nominee_phone'];
      $nomineeGpa         = $context['nominee_gpa'];
      $nomineeClass       = $context['nominee_class'];
      $nomineeResponsibility = $context['nominee_responsibility']; // jeremy sorry you can fix it bro


      /*************
       * Nominator *
       *************/
      $nominatorFirstName    = $context['nominator_first_name'];
      $nominatorMiddleName   = $context['nominator_middle_name'];
      $nominatorLastName     = $context['nominator_last_name'];
      $nominatorAddress      = $context['nominator_address'];
      $nominatorPhone        = $context['nominator_phone'];
      $nominatorEmail        = $context['nominator_email'] . '@appstate.edu';
      $nominatorRelation     = $context['nominator_relationship'];
      $nominatorUniqueId     = $context['nominator_unique_id'];


      /**************
       * Nomination *
      **************/
      $category = $context['category'];
      $period = Period::getCurrentPeriod();
      //we need this cause we're adding the period's "number" not the period object itself
      $period_id = $period->getId();
      $nomination = new Nomination($nomineeBannerId, $nomineeFirstName, $nomineeMiddleName, $nomineeLastName,
                      $nomineeEmail, $nomineeAsubox, $nomineePosition, $nomineeDeptMajor, $nomineeYears,
                      $nomineePhone, $nomineeGpa, $nomineeClass, $nomineeResponsibility,
                      $nominatorFirstName, $nominatorMiddleName, $nominatorLastName, $nominatorAddress,
                      $nominatorPhone, $nominatorEmail, $nominatorRelation, $nominatorUniqueId, $category, $period_id);
      // Save the nomination to the db; If this works,
      // the factory will populate the $nomination with its database id.

      $nomination->setId($oldNomination->getId());
      NominationFactory::save($nomination);



      /**************
       * References *
       **************/
       /*
       // TODO Fix reference editing
      $numRefsReq = Reference::getNumReferencesReq();
      $updatedRefsNeedEmail = array();

      for($i = 0; $i < $numRefsReq; $i++)
      {
        $refId = $context['reference_id_'.$i];
        $ref = ReferenceFactory::getReferenceById($refId); // TODO: The $refId is a database id (i.e. an integer), not a string, so this fails
        $changed = 0;

        if($ref->getFirstName() != $context['reference_first_name_'.$i])
        {
          $ref->setFirstName($context['reference_first_name_'.$i]);
          $changed = 1;
        }

        if($ref->getLastName() != $context['reference_last_name_'.$i])
        {
          $ref->setLastName($context['reference_last_name_'.$i]);
          $changed = 1;
        }

        if($ref->getDepartment() != $context['reference_department_'.$i])
        {
          $ref->setDepartment($context['reference_department_'.$i]);
          $changed = 1;
        }

        if($ref->getPhone() != $context['reference_phone_'.$i])
        {
          $ref->setPhone($context['reference_phone_'.$i]);
          $changed = 1;
        }

        if($ref->getEmail() != $context['reference_email_'.$i])
        {
          $ref->setEmail($context['reference_email_'.$i]);
          array_push($updatedRefsNeedEmail, $refId);
          $changed = 1;
        }

        if($ref->getRelationship() != $context['reference_relationship_'.$i])
        {
          $ref->setRelationship($context['reference_relationship_'.$i]);
          $changed = 1;
        }

        if($changed)
        {
          ReferenceFactory::save($ref);
        }

      }
      */

      /***************
       * Send Emails *
      ***************/

      /*
      // TODO: Bring this back when reference editing is fixed
      foreach($updatedRefsNeedEmail as $refId)
      {
          $ref = ReferenceFactory::getReferenceById($refId);
          ReferenceEmail::updateNomination($ref, $nomination);
      }
      */


      \NQ::simple('Nomination', NotificationView::NOMINATION_SUCCESS, 'Form successfully submitted. Changes made.');


    }
}
