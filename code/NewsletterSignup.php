<?php
use \DrewM\MailChimp\MailChimp;

class NewsletterSignup extends EditableFormField
{
    public static $singular_name = 'Newsletter Signup Field';
    public static $plural_name = 'Newsletter Signup Fields';
    private static $api_key = "";
    private static $db = array(
        'ListId' => 'Varchar(255)',
        'EmailField' => 'Varchar(255)',
        'FirstNameField' => 'Varchar(255)',
        'LastNameField' => 'Varchar(255)',
        'TickedByDefault' => 'Boolean',
        'HideOptIn' => 'Boolean',
        'ShowGroupsInterests' => 'Boolean',
        'DefaultInterest' => 'Varchar(255)'
    );
    private static $has_many = array(
        "MailChimpMergeVars" => "MailChimpMergeVar"
    );
    public function Icon()
    {
        return MOD_DOAP_DIR . '/images/editablemailchimpsubscriptionfield.png';
    }
    public static function set_api_key($key)
    {
        self::$api_key = $key;
    }

    public static function get_api_key()
    {
        return self::$api_key;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Default');
        $fields->removeByName('Validation');

        $fieldsStatus = true;
        if ($this->Lists()->Count() > 0) {
            $fieldsStatus = false;
        }
      $fields->addFieldsToTab("Root.Main", [
        LiteralField::create("MailChimpStart", "<h4>Mailchimp Configuration</h4>")->setAttribute("disabled", $fieldsStatus),
        DropdownField::create("ListId", 'Subscribers List', $this->Lists()->map("id", "name"))
              ->setEmptyString("Choose a List")
              ->setAttribute("disabled", $fieldsStatus),
        CheckboxField::create("TickedByDefault")->setAttribute("disabled", $fieldsStatus),
        CheckboxField::create("HideOptIn")->setAttribute("disabled", $fieldsStatus),
        CheckboxField::create("ShowGroupsInterests")->setAttribute("disabled", $fieldsStatus),
        DropdownField::create("EmailField", 'Email Field', $this->CurrentFormFields())->setAttribute("disabled", $fieldsStatus),
        DropdownField::create("FirstNameField", 'First Name Field', $this->CurrentFormFields())->setAttribute("disabled", $fieldsStatus),
        DropdownField::create("LastNameField", 'Last Name Field', $this->CurrentFormFields())->setAttribute("disabled", $fieldsStatus),
        GroupedDropdownField::create("DefaultInterest", 'Add to Interest', $this->InterestsOptions())
                 ->setEmptyString("Choose an Interest")
                 ->setAttribute("disabled", $fieldsStatus),

      ]);
      $config =  GridFieldConfig_RelationEditor::create();
      $dataColumns = $config->getComponentByType('GridFieldDataColumns');
      $dataColumns->setDisplayFields(array(
         'FormField' => 'FormField',
         'MergeField'=> 'MergeField'
      ));
      $fields->addFieldToTab('Root.MergeVars',
        $grid =   new GridField('MailChimpMergeVars', 'MailChimpMergeVar', $this->MailChimpMergeVars(),  $config)
      );

      return $fields;
    }

    public function CurrentFormFields(){
      return $this->Parent()->Fields()->map('Name', 'Title')->toArray();
    }

    function Lists(){
        $MailChimp = new MailChimp($this->get_api_key());
        $lists = $MailChimp->get("lists/");
        $mLists= [];
        foreach($lists['lists'] as $list){
          $mLists[] = new ArrayData(["id" => $list["id"], "name" => $list["name"]]);
        }
        return new ArrayList($mLists);
    }

    public function InterestsOptions(){
      $MailChimp = new MailChimp($this->get_api_key());
      $categories = $MailChimp->get("lists/{$this->ListId}/interest-categories");
      $mCategories= [];
      foreach($categories['categories'] as $category){
        $mInterests = [];
        $interests = $MailChimp->get("lists/{$this->ListId}/interest-categories/{$category["id"]}/interests");
        foreach ($interests["interests"] as $interest) {
          $mInterests[$interest["id"]] =   $interest["name"];
        }
        $mCategories[$category["title"]] =  $mInterests;
      }
      return $mCategories;
    }

    public function MergeFields(){
      $MailChimp = new MailChimp($this->get_api_key());
      $response = $MailChimp->get("lists/{$this->ListId}/merge-fields");
      $result = [];
      foreach ($response["merge_fields"] as $merge_field) {
        $result[$merge_field["tag"]] =  $merge_field["name"];
      }
      return $result;
    }


    // public function MergeVars(){
    //   $MailChimp = new MailChimp($this->get_api_key());
    //   $mergevars = $MailChimp->get("lists/{$this->ListId}/merge-fields");
    //   if (is_array($mergevars['merge_fields'])) {
    //       $map_mergevars = array();
    //       $i = 0;
    //       foreach ($mergevars['merge_fields'] as $mv) {
    //           $map_mergevars[$i]['name'] = $mv['name'];
    //           $map_mergevars[$i]['tag'] = $mv['tag'];
    //           $i++;
    //       }
    //   }
    //   return $map_mergevars;
    // }
    public function Interests(){
        $MailChimp = new MailChimp($this->get_api_key());
        $categories = $MailChimp->get("lists/{$this->ListId}/interest-categories");
        $mCategories= [];
        foreach($categories['categories'] as $category){
          $mInterests = [];
          $interests = $MailChimp->get("lists/{$this->ListId}/interest-categories/{$category["id"]}/interests");
          foreach ($interests["interests"] as $interest) {
            $mInterests[$interest["id"]] =   $interest["name"];
          }
          $mCategories[] = ["id" => $category["id"], "title" => $category["title"], "interests" => $mInterests];
        }
        return $mCategories;
    }
    //
    // public function Groups(){
    //     $MailChimp = new MailChimp($this->get_api_key());
    //     $categories = $MailChimp->get("lists/{$this->ListId}/interest-categories");
    //     $mCategories= [];
    //     foreach($categories['categories'] as $group){
    //       $interests = $MailChimp->get("lists/{$this->ListId}/interest-categories/{$interests["id"]}");
    //       $mInterests = [];
    //       foreach ($interests as $interest) {
    //         $mInterests[] = new ArrayData(["id" => $interest["id"], "name" => $interest["name"]]);
    //       }
    //       $mCategories[] = new ArrayData(["id" => $interests["id"], "title" => $interests["title"], "interests" => $mInterests]);
    //     }
    //     return new ArrayList($mCategories);
    // }



    public function getFormField()
    {
      if($this->ListId && $this->FirstNameField && $this->EmailField && $this->LastNameField){
        $f = new FieldGroup(
          $this->optin_field(),
          $this->groups_field()
        );
        return $f;
      }

      // //$map_groups = $this->Groups();
      // if (count($map_groups) > 1) {
      //   Requirements::javascript(MOD_DOAP_DIR . "/javascript/newsletter.js");
      //   $f = new FieldGroup(
      //       $a = new CheckboxField($this->Name, $this->Title, $this->getSetting('Default')),
      //       new CheckboxSetField('Themes', 'Choose Groups', $map_groups)
      //   );
      //   $a->addExtraClass('newsletter-toggle');
      //   $f->addExtraClass('newsletter-group');
      //   return $f;
      // }else{
      //   return null;
      // }
    }
    public function groups_field(){
      if($this->ShowGroupsInterests){
        $fields = new FieldGroup();
        foreach($this->Interests() as  $interests){
            debug::show($interests["interests"]);
            $fields->push(CheckboxSetField::create("Interests{$interests["id"]}", "Choose {$interests["title"]}", $interests["interests"]));
        }
        return $fields;
      }else{
        return null;
      }
    }
    public function optin_field(){
      if ($this->HideOptIn){
        return new HiddenField($this->Name, $this->Title, true);
      }else{
        return new CheckboxField($this->Name, $this->Title, $this->TickedByDefault);
      }
    }

    private function getNewsLetterFieldNames()
    {
        $values = array();
        foreach ($this->MergeVars as $maper) {
            foreach ($this->Parent()->Fields() as $field) {
                if ($maper['name'] == $field->Title) {
                    $values[$maper['tag']]['name'] = $field->Name;
                    $values[$maper['tag']]['title'] = $field->Title;
                }
            }
        }
        return $values;
    }

    public function getValueFromData($data)
    {
        $data = Session::get("FormInfo.Form_Form.data");
        $map = $this->getNewsLetterFieldNames();
        $value = (isset($data[$this->Name])) ? $data[$this->Name] : false;

        if ($value) {
            $MailChimp = new MailChimp($this->get_api_key());
            $result = $MailChimp->call('lists/subscribe', array(
                'id' => $this->ListId,
                'email' => array('email' => $data[$map['EMAIL']['name']]),
                'merge_vars' => array('FNAME' => $data[$map['FNAME']['name']], 'LNAME' => $data[$map['LNAME']['name']]),
                'double_optin' => false,
                'update_existing' => true,
                'replace_interests' => false,
                'send_welcome' => true,
            ));
            return ($value) ? $value : _t('EditableFormField.NO', 'No');
        }
    }
}
