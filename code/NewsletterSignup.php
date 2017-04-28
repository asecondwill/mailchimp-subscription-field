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
      'DoubleOptin' => 'Boolean',
      'SendWelcome' => 'Boolean',
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
              ->setAttribute("disabled", $fieldsStatus)]
      );
      if (!empty($this->ListId)){
        $fields->addFieldsToTab("Root.Main", [
          CheckboxField::create("TickedByDefault")->setAttribute("disabled", $fieldsStatus),
          CheckboxField::create("HideOptIn")->setAttribute("disabled", $fieldsStatus),
          CheckboxField::create("DoubleOptin")->setAttribute("disabled", $fieldsStatus),
          CheckboxField::create("SendWelcome")->setAttribute("disabled", $fieldsStatus),
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
      }


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
      if(!empty($this->ListId)){
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
    }

    public function MergeFields(){
      if(!empty($this->ListId)){
        $MailChimp = new MailChimp($this->get_api_key());
        $response = $MailChimp->get("lists/{$this->ListId}/merge-fields");
        $result = [];
        foreach ($response["merge_fields"] as $merge_field) {
          $result[$merge_field["tag"]] =  $merge_field["name"];
        }
        return $result;
      }
    }

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

    public function getFormField()
    {
      Requirements::javascript(MOD_DOAP_DIR . "/javascript/newsletter.js");
      if($this->ListId && $this->FirstNameField && $this->EmailField && $this->LastNameField){
        $f = new FieldGroup(
          $this->optin_field(),
          $this->groups_field()
        );
        return $f;
      }
    }

    public function groups_field(){
      if($this->ShowGroupsInterests){
        $fields = new FieldGroup();
        foreach($this->Interests() as  $interests){
          $fields->push($field = CheckboxSetField::create("Interests{$interests["id"]}", "Choose {$interests["title"]}", $interests["interests"]));
        }
        $fields->addExtraClass('newsletter-group');
        return $fields;
      }else{
        return null;
      }
    }

    public function optin_field(){
      if ($this->HideOptIn){
        return new HiddenField($this->Name, $this->Title, true);
      }else{
        $field = new CheckboxField($this->Name, $this->EscapedTitle, $this->TickedByDefault);
        $field->addExtraClass('newsletter-toggle');
        return $field;
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

    public function get_interests_from_data($data){
      $result = [];
      foreach($data as $iterests){
        if (is_array($iterests)){
          $iterests = array_values($iterests);
          foreach ($iterests as $item){
            $result[$item]  = true;
          }
        }
      }
      return $result;
    }

    public function merge_fields($data){
      $result = [];
      $result['FNAME'] = $data[$this->FirstNameField];
      $result['LNAME'] = $data[$this->LastNameField];
      foreach ($this->MailChimpMergeVars() as $var) {
        $result[$var->MergeField] = $data[$var->FormField];
      }
      return $result;
    }

    public function getValueFromData($data)
    {
      if($data[$this->Name]){
        $mc_data = [
          'email_address'   => $data[$this->EmailField],
				  'status'          => 'subscribed',
          'double_optin'    => $this->DoubleOptin,
          'send_welcome'    => $this->SendWelcome,
          'update_existing' => true,
          'merge_fields'    => $this->merge_fields($data)
        ];
        if (!empty($this->get_interests_from_data($data))){
          $mc_data['interests'] = $this->get_interests_from_data($data);
        }
        $MailChimp = new MailChimp($this->get_api_key());
        $result = $MailChimp->post("lists/{$this->ListId}/members", $mc_data);
        if(isset($result['errors']) && is_array($result['errors'])){
          SS_Log::log("oops, mailchimp  error {$result['errors'][0]['message']}" , SS_Log::WARN);
          SS_Log::log(var_export($mc_data, true), SS_Log::WARN);
          return false;
        }else{
          return true;
        }
      }
    }
}
