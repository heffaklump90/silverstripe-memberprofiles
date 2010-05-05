<?php
/**
 * A MemberProfilePage allows the administratior to set up a page with a subset of the
 * fields available on the member object, then allow members to register and edit
 * their profile using these fields.
 *
 * It also supports email validation.
 *
 * @package silverstripe-memberprofiles
 */
class MemberProfilePage extends Page {

	public static $icon = 'memberprofiles/images/memberprofilepage';

	public static $db = array (
		'ProfileTitle'             => 'Varchar(255)',
		'RegistrationTitle'        => 'Varchar(255)',
		'AfterRegistrationTitle'   => 'Varchar(255)',
		'ProfileContent'           => 'HTMLText',
		'RegistrationContent'      => 'HTMLText',
		'AfterRegistrationContent' => 'HTMLText',
		'AllowRegistration'        => 'Boolean',

		'EmailValidation'     => 'Boolean',
		'EmailFrom'           => 'Varchar(255)',
		'EmailSubject'        => 'Varchar(255)',
		'EmailTemplate'       => 'Text',
		'ConfirmationTitle'   => 'Varchar(255)',
		'ConfirmationContent' => 'HTMLText'
	);

	public static $has_many = array (
		'Fields' => 'MemberProfileField'
	);

	public static $many_many = array (
		'Groups' => 'Group',
		'SelectableGroups' => 'Group'
	);

	public static $defaults = array (
		'ProfileTitle'             => 'Edit Profile',
		'RegistrationTitle'        => 'Register / Log In',
		'AfterRegistrationTitle'   => 'Registration Successful',
		'AfterRegistrationContent' => '<p>Thank you for registering!</p>',
		'AllowRegistration'        => true,
		'EmailValidation'          => true,
		'ConfirmationTitle'        => 'Account Confirmed',
		'ConfirmationContent'      => '<p>Your account is now active, and you have been logged in. Thankyou!</p>'
	);

	/**
	 * An array of member profile fields that should be editable. All others will be set to NOT
	 * be editable in either reg or profile update (fields can be enabled manually later) 
	 *
	 * @var array
	 */
	public static $default_editable_member_fields = array(
		'Email' => true,
		'FirstName' => true,
		'Surname' => true,
		'Password' => true
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldToTab('Root', $validation = new Tab('Validation'), 'Behaviour');
		$fields->addFieldToTab('Root.Content', $profileContent = new Tab('Profile'), 'Metadata');
		$fields->addFieldToTab('Root.Content', $regContent = new Tab('Registration'), 'Metadata');
		$fields->addFieldToTab('Root.Content', $afterReg = new Tab('AfterRegistration'), 'Metadata');

		$validation->setTitle(_t('MemberProfiles.VALIDATION', 'Validation'));
		$profileContent->setTitle(_t('MemberProfiles.PROFILE', 'Profile'));
		$regContent->setTitle(_t('MemberProfiles.REGISTRATION', 'Registration'));
		$afterReg->setTitle(_t('MemberProfiles.AFTERRED', 'After Registration'));

		foreach(array('Profile', 'Registration', 'AfterRegistration') as $tab) {
			$fields->addFieldToTab (
				"Root.Content.$tab",
				new TextField("{$tab}Title", _t('MemberProfiles.TITLE', 'Title'))
			);

			$fields->addFieldToTab (
				"Root.Content.$tab",
				new HtmlEditorField("{$tab}Content", _t('MemberProfiles.CONTENT', 'Content'))
			);
		}

		$fields->removeFieldFromTab('Root.Content.Main', 'Title');
		$fields->removeFieldFromTab('Root.Content.Main', 'Content');

		$fields->addFieldsToTab('Root.Content.Main', new HeaderField (
			'FieldsHeader', _t('MemberProfiles.PROFILEREGFIELDS', 'Profile/Registration Fields')
		));
		$fields->addFieldToTab('Root.Content.Main', $fieldsTable = new OrderableCTF (
			$this,
			'Fields',
			'MemberProfileField'
		));

		$fieldsTable->setPermissions(array('show', 'edit'));
		$fieldsTable->setCustomSourceItems($this->getProfileFields());
		$fieldsTable->setShowPagination(false);

		$validation->push(new HeaderField (
			'EmailValidHeader', _t('MemberProfiles.EMAILVALIDATION', 'Email Validation')
		));
		$validation->push(new CheckboxField (
			'EmailValidation', _t('MemberProfiles.EMAILVALID', 'Require email validation')
		));
		$validation->push(new TextField (
			'EmailSubject', _t('MemberProfiles.VALIDEMAILSUBJECT', 'Validation email subject')
		));
		$validation->push(new TextField (
			'EmailFrom', _t('MemberProfiles.EMAILFROM', 'Email from')
		));
		$validation->push(new TextareaField (
			'EmailTemplate', _t('MemberProfiles.EMAILTEMPLATE', 'Email template')
		));
		$validation->push(new LiteralField (
			'TemplateNote', MemberConfirmationEmail::TEMPLATE_NOTE
		));
		$validation->push(new HeaderField (
			'ConfirmationContentHeader', _t('MemberProfiles.CONFIRMCONTENT', 'Confirmation Content')
		));
		$validation->push(new LiteralField (
			'ConfirmationNote',
			'<p>' . _t (
				'MemberProfiles.CONFIRMNOTE',
				'This content is displayed when a user confirms their account.'
			) . '</p>'
		));
		$validation->push(new TextField (
			'ConfirmationTitle', _t('MemberProfiles.TITLE', 'Title')
		));
		$validation->push(new HtmlEditorField (
			'ConfirmationContent', _t('MemberProfiles.CONTENT', 'Content')
		));

		$fields->addFieldToTab (
			'Root.Behaviour',
			new HeaderField (
				'RegSettingsHeader', _t('MemberProfiles.REGSETTINGS', 'Registration Settings')
			),
			'ClassName'
		);
		$fields->addFieldToTab (
			'Root.Behaviour',
			new CheckboxField (
				'AllowRegistration', _t('MemberProfiles.ALLOWREG', 'Allow registration via this page')
			),
			'ClassName'
		);
		$fields->addFieldToTab (
			'Root.Behaviour',
			new HeaderField (
				'PageSettingsHeader', _t('MemberProfiles.PAGESETTINGS', 'Page Settings')
			),
			'ClassName'
		);

		$fields->addFieldToTab('Root.Content.Main', new HeaderField (
			'GroupSettingsHeader', _t('MemberProfiles.GROUPSETTINGS', 'Group Settings')
		));
		$fields->addFieldToTab('Root.Content.Main', new LiteralField (
			'GroupsNote',
			_t (
				'MemberProfiles.GROUPSNOTE',
				'<p>Any users registering via this page will always be added to the below groups (if ' .
				'registration is enabled). Conversely, a member must belong to these groups '   .
				'in order to edit their profile on this page.</p>'
			)
		));
		$fields->addFieldToTab('Root.Content.Main', new CheckboxSetField (
			'Groups', '', DataObject::get('Group')->map()
		));

		$fields->addFieldToTab('Root.Content.Main', new LiteralField (
			'SelectableGroupsNote',
			_t (
				'MemberProfiles.SELECTABLE_GROUPSNOTE',
				'<p>Users can choose to belong to the following groups, if the "Groups" field is enabled in the '.
				'above</p>'
			)
		));
		$fields->addFieldToTab('Root.Content.Main', new CheckboxSetField (
			'SelectableGroups', '', DataObject::get('Group')->map()
		));

		return $fields;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		// make sure that we have a menu title and title!
		$this->Title = $this->MenuTitle;
	}

	/**
	 * Get either the default or custom email template.
	 *
	 * @return string
	 */
	public function getEmailTemplate() {
		return ($t = $this->getField('EmailTemplate')) ? $t : MemberConfirmationEmail::DEFAULT_TEMPLATE;
	}

	/**
	 * Get either the default or custom email subject line.
	 *
	 * @return string
	 */
	public function getEmailSubject() {
		return ($s = $this->getField('EmailSubject')) ? $s : MemberConfirmationEmail::DEFAULT_SUBJECT;
	}

	/**
	 * Get a set of all the {@link MemberProfileFields} available, automatically synced with the
	 * state of the Member object.
	 *
	 * @return DataObjectSet
	 */
	public function getProfileFields() {
		$set        = $this->Fields();
		$fields     = singleton('Member')->getMemberFormFields()->dataFields();
		$setNames   = $set->map('ID', 'MemberField');
		$fieldNames = array_keys($fields);

		foreach($set as $field) {
			if(!in_array($field->MemberField, $fieldNames)) {
				$set->remove($field);
			}
		}

		foreach($fields as $name => $field) {
			if(in_array($name, $setNames)) continue;

			$profileField = new MemberProfileField();
			$profileField->MemberField   = $name;
			$profileField->ProfilePageID = $this->ID;

			if (!isset(self::$default_editable_member_fields[$name])) {
				$profileField->ProfileVisibility = "Hidden";
				$profileField->RegistrationVisibility = "Hidden";
			}

			$profileField->write();

			$set->add($profileField);
		}

		return $set;
	}

}

/**
 *
 */
class MemberProfilePage_Controller extends Page_Controller {

	public static $allowed_actions = array (
		'index',
		'RegisterForm',
		'ProfileForm',
		'confirm'
	);

	/**
	 * @uses   MemberProfilePage_Controller::indexRegister
	 * @uses   MemberProfilePage_Controller::indexProfile
	 * @return array
	 */
	public function index() {
		return Member::currentUser() ? $this->indexProfile() : $this->indexRegister();
	}

	/**
	 * Allow users to register if registration is enabled.
	 *
	 * @return array
	 */
	protected function indexRegister() {
		if(!$this->AllowRegistration) return Security::permissionFailure($this, _t (
			'MemberProfiles.CANNOTREGPLEASELOGIN',
			'You cannot register on this profile page. Please login to edit your profile.'
		));

		return array (
			'Title' => $this->obj('RegistrationTitle'),
			'Content' => $this->obj('RegistrationContent'),
			'Form' => $this->RegisterForm()
		);
	}

	/**
	 * Allows users to edit their profile if they are in at least one of the groups this page is
	 * restricted to.
	 *
	 * @return array
	 */
	protected function indexProfile() {
		$member = Member::currentUser();

		foreach($this->Groups() as $group) {
			if(!$member->inGroup($group)) {
				return Security::permissionFailure($this);
			}
		}

		$form = $this->ProfileForm();
		$form->loadDataFrom($member);

		return array (
			'Title' => $this->obj('ProfileTitle'),
			'Content' => $this->obj('ProfileContent'),
			'Form'  => $form
		);
	}

	/**
	 * @uses   MemberProfilePage_Controller::getProfileFields
	 * @return Form
	 */
	public function RegisterForm() {
		$form = new Form (
			$this,
			'RegisterForm',
			$this->getProfileFields('Registration'),
			new FieldSet (
				new FormAction('register', _t('MemberProfiles.REGISTER', 'Register'))
			),
			new MemberProfileValidator($this->Fields())
		);

		if(class_exists('SpamProtectorManager')) {
			SpamProtectorManager::update_form($form);
		}

		return $form;
	}

	/**
	 * Handles validation and saving new Member objects, as well as sending out validation emails.
	 */
	public function register($data, Form $form) {
		$member = new Member();

		$groupIds = $this->getSettableGroupIdsFrom($form);

		$form->saveInto($member);
		$member->ProfilePageID = $this->ID;

		try {
			$member->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return Director::redirectBack();
		}

		// set after member is created otherwise the member object does not exist
		$member->Groups()->setByIDList($groupIds);

		if($this->EmailValidation) {
			$email = new MemberConfirmationEmail($this, $member);
			$email->send();

			$member->NeedsValidation = true;
			$member->write();
		} else {
			$member->logIn();
		}

		return array (
			'Title' => $this->obj('AfterRegistrationTitle'),
			'Content' => $this->obj('AfterRegistrationTitle'),
		);
	}

	/**
	 * @uses   MemberProfilePage_Controller::getProfileFields
	 * @return Form
	 */
	public function ProfileForm() {
		return new Form (
			$this,
			'ProfileForm',
			$this->getProfileFields('Profile'),
			new FieldSet (
				new FormAction('save', _t('MemberProfiles.SAVE', 'Save'))
			),
			new MemberProfileValidator($this->Fields(), Member::currentUser())
		);
	}

	/**
	 * Updates an existing Member's profile.
	 */
	public function save(array $data, Form $form) {
		$member = Member::currentUser();

		$groupIds = $this->getSettableGroupIdsFrom($form);
		$member->Groups()->setByIDList($groupIds);

		$form->saveInto($member);

		try {
			$member->write();
		} catch(ValidationException $e) {
			$form->sessionMessage($e->getResult()->message(), 'bad');
			return Director::redirectBack();
		}

		$form->sessionMessage (
			_t('MemberProfiles.PROFILEUPDATED', 'Your profile has been updated.'),
			'good'
		);
		return Director::redirectBack();
	}

	/**
	 * Gets the list of groups that can be set after the submission of a particular form
	 *
	 * This works around the problem with the checkboxsetfield which doesn't validate that the
	 * groups that the user has selected are not validated against the list of groups the user is
	 * allowed to choose from. 
	 *
	 * @param Form $form
	 */
	protected function getSettableGroupIdsFrom(Form $form) {
		// first off check to see if groups were selected by the user. If so, we want
		// to remove that control from the form list (just in case someone's sent through an
		// ID for a group like, say, the admin's group...). It means we have to handle the setting
		// ourselves, but that's okay
		$groupField = $form->Fields()->dataFieldByName('Groups');
		// The list of selectable groups
		$groupIds = $allowedIds = $this->SelectableGroups()->map('ID', 'ID');

		// we need to track the selected groups against the existing user's groups - this is
		// so that we don't accidentally remove them from the list of groups
		// a user might have been placed in via other means
		$existingIds = array();
		if (Member::currentUserID()) {
			$existing = Member::currentUser()->Groups();
			if ($existing && $existing->Count() > 0) {
				$existingIds = $existing->map('ID', 'ID');
				// remove any that are in the selectable groups map - we only want to 
				// worry about those that aren't managed by this form
				foreach ($groupIds as $gid) {
					unset($existingIds[$gid]);
				}
			}
		}

		if ($groupField) {
			$givenIds = $groupField->Value();
			$groupIds = array();
			if ($givenIds) {
				foreach ($givenIds as $givenId) {
					if (isset($allowedIds[$givenId])) {
						$groupIds[] = $givenId;
					}
				}
			}
			$form->Fields()->removeByName('Groups');
		}

		foreach ($this->Groups()->column('ID') as $mustId) {
			$groupIds[] = $mustId;
		}

		foreach ($existingIds as $existingId) {
			if (!in_array($existingId, $groupIds)) {
				$groupIds[] = $existingId;
			}
		}

		return $groupIds;
	}

	/**
	 * Allows the user to confirm their account by clicking on the validation link in
	 * the confirmation email.
	 *
	 * @param  HTTPRequest $request
	 * @return array
	 */
	public function confirm($request) {
		if(Member::currentUser()) {
			return Security::permissionFailure ($this, _t (
				'MemberProfiles.CANNOTCONFIRMLOGGEDIN',
				'You cannot confirm account while you are logged in.'
			));
		}

		if (
			!$this->EmailValidation
			|| (!$id = $request->param('ID')) || (!$key = $request->getVar('key')) || !is_numeric($id)
			|| !$member = DataObject::get_by_id('Member', $id)
		) {
			$this->httpError(404);
		}

		if($member->ValidationKey != $key || !$member->NeedsValidation) {
			$this->httpError(403, 'You cannot validate this member.');
		}

		$member->NeedsValidation = false;
		$member->ValidationKey   = null;
		$member->write();

		$member->logIn();

		return array (
			'Title'   => $this->obj('ConfirmationTitle'),
			'Content' => $this->obj('ConfirmationContent')
		);
	}

	/**
	 * @param  string $context
	 * @return FieldSet
	 */
	protected function getProfileFields($context) {
		$profileFields = $this->Fields();
		$member        = Member::currentUser() ? Member::currentUser() : singleton('Member');
		$memberFields  = $member->getMemberFormFields();
		$fields        = new FieldSet();

		if($context == 'Registration') {
			$fields->push(new HeaderField (
				'LogInHeader', _t('MemberProfiles.LOGIN_HEADER', 'Log In')
			));

			$fields->push(new LiteralField (
				'LogInNote',
				'<p>' . sprintf (
					_t (
						'MemberProfiles.LOGIN',
						'If you already have an account you can <a href="%s">log in here</a>.'
					),
					Security::Link('login') . '?BackURL=' . $this->Link()
				) . '</p>'
			));

			$fields->push(new HeaderField (
				'RegisterHeader', _t('MemberProfiles.REGISTER', 'Register')
			));
		}

		foreach($profileFields as $profileField) {
			$visibility  = $profileField->{$context . 'Visibility'};
			$name        = $profileField->MemberField;
			$memberField = $memberFields->dataFieldByName($name);

			// handle the special case of the Groups control so that only allowed groups can be selected
			if ($name == 'Groups') {
				$availableGroups = $this->data()->SelectableGroups();
				$memberField->setSource($availableGroups);
			}

			if(!$memberField || $visibility == 'Hidden') continue;

			$field = clone $memberField;
			$field->setTitle($profileField->Title);
			$field->setRightTitle($profileField->Note);

			if($context == 'Registration' && $profileField->DefaultValue) {
				$field->setValue($profileField->DefaultValue);
			}

			if($profileField->CustomError) {
				$field->setCustomValidationMessage($profileField->CustomError);
			}

			if($visibility == 'Readonly') {
				$field = $field->performReadonlyTransformation();
			}

			$fields->push($field);
		}

		$this->extend('updateProfileFields', $fields);
		return $fields;
	}

}