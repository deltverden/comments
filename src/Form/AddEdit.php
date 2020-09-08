<?php

namespace Drupal\comments\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;

class AddEdit extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'addcomments';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0
  ) {

    $form_state->set('commentAvatar', 'default.png');
    $form_state->set('commentImage', NULL);

    // If $id > 0, the comment exists, so we take the values from the database.
    if ($id > 0) {
      $query = \Drupal::database()->select('a_comments', 'acom');
      $query->fields(
        'acom', ['name', 'email', 'phone', 'text', 'avatar', 'image']
      );
      $query->condition('id', $id, '=');
      $result = $query->execute()->fetchAll();

      if (empty($result)) {
        \Drupal::messenger()->addMessage(
          "Comment with ID: $id is not found!", 'warning'
        );

        header("Location: /" . 'comments');
        die();
      }

      $comment = [
        'title' => '',
        'email' => '',
        'phone' => '',
        'content' => '',
        'avatar' => '',
        'image' => '',
      ];

      foreach ($result as $row) {
        $comment = [
          'title' => $row->name,
          'email' => $row->email,
          'phone' => $row->phone,
          'content' => $row->text,
          'avatar' => $row->avatar,
          'image' => $row->image,
        ];
      }

      $form_state->set('commentID', $id);
      $form_state->set('commentTitle', $comment['title']);
      $form_state->set('commentEmail', $comment['email']);
      $form_state->set('commentPhone', $comment['phone']);
      $form_state->set('commentContent', $comment['content']);
      $form_state->set('commentAvatar', $comment['avatar']);
      $form_state->set('commentImage', $comment['image']);
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => 'Name',
      '#required' => TRUE,
      '#placeholder' => 'First Name Last Name',
      // Value 'commentTitle' from the database if it exists, otherwise an empty value.
      '#default_value' => $form_state->get('commentTitle') ?? '',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => 'Email',
      '#required' => TRUE,
      '#placeholder' => 'admin@gmail.com',
      '#default_value' => $form_state->get('commentEmail') ?? '',
    ];

    $form['phone'] = [
      '#type' => 'tel',
      '#title' => 'Phone',
      '#required' => TRUE,
      '#placeholder' => '(000) 000-0000',
      '#default_value' => $form_state->get('commentPhone') ?? '',
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => 'Comment text',
      '#required' => TRUE,
      '#cols' => 60,
      '#rows' => 13,
      '#default_value' => $form_state->get('commentContent') ?? '',
    ];

    $form['avatar'] = [
      '#type' => 'managed_file',
      '#title' => 'Avatar',
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [1024 * 2048],
      ],
      '#upload_location' => 'public://comments/avatars',
    ];

    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => 'Image',
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [1024 * 5120],
      ],
      '#upload_location' => 'public://comments/images',
    ];

    $form['action']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => 'Save',
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitCallback(
    array &$form, FormStateInterface $form_state
  ) {
    $response = new AjaxResponse();

    $addmessage = [
      '#theme' => 'status_messages',
      '#message_list' => drupal_get_messages(),
      '#status_headings' => [
        'status' => t('Status message'),
        'error' => t('Error message'),
        'warning' => t('Warning message'),
      ],
    ];

    // Rendering different types of messages.
    $updateForm = \Drupal::service('renderer')->render($addmessage);

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(
        new HtmlCommand('#form-system-messages', $updateForm)
      );
    }
    else {
      $response->addCommand(new RedirectCommand("/" . 'comments'));
      if ($form_state->get('commentID')) {
        \Drupal::messenger()->addMessage(
          "Comment '{$form_state->getValue('name')}' with ID: {$form_state->get('commentID')} has been edited!",
          'status'
        );
      }
      else {
        \Drupal::messenger()->addMessage(
          "Comment '{$form_state->getValue('name')}' has been created!",
          'status'
        );
      }
    }

    $this->setPhotoPermanent('avatar', $form_state);
    $this->setPhotoPermanent('image', $form_state);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Finds the 'avatar' file and write it's name or write the default value.
    $formavatar = $form_state->getValue('avatar');
    if ($formavatar) {
      $avatarFilename = \Drupal::entityTypeManager()->getStorage('file')
        ->load($form_state->getValue('avatar')[0]);
      $avatarFilename = $avatarFilename->get('filename')->value;
      $form_state->set('avatarFilename', $avatarFilename);
    }
    else {
      $form_state->set('avatarFilename', $form_state->get('commentAvatar'));
    }

    // Finds the 'image' file and write it's name or write the default value.
    $formimage = $form_state->getValue('image');
    if ($formimage) {
      $imageFilename = \Drupal::entityTypeManager()->getStorage('file')
        ->load($form_state->getValue('image')[0]);
      $imageFilename = $imageFilename->get('filename')->value;
      $form_state->set('imageFilename', $imageFilename);
    }
    else {
      $form_state->set('imageFilename', $form_state->get('commentImage'));
    }

    // Phone number validation. Example: (123) 123-1234.
    $phone = $form_state->getValue('phone');
    if (isset($phone)) {
      $preg = "/\(\d{3}\) \d{3}-\d{4}/";
      preg_match($preg, $phone, $resultPhone);
      if ($resultPhone == FALSE) {
        $form_state->setErrorByName(
          'phone',
          $this->t(
            "The phone number is not correct.<br>Example phone number: '(000) 000-0000'."
          )
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->get('commentID');

    // If $id > 0 - update comment to the database from form.
    if ($id > 0) {
      $query = \Drupal::database()->update('a_comments');
      $query->condition('id', $id, '=');
      $query->fields(
        [
          'name' => "{$form_state->getValue('name')}",
          'email' => "{$form_state->getValue('email')}",
          'phone' => "{$form_state->getValue('phone')}",
          'text' => "{$form_state->getValue('text')}",
          'avatar' => "{$form_state->get('avatarFilename')}",
          'image' => "{$form_state->get('imageFilename')}",
        ]
      );

      $query->execute();
      \Drupal::messenger()->addMessage(
        "Comment '{$form_state->getValue('name')}' with ID: $id has been edited!",
        'status'
      );
    }
    // If $id <= 0 - insert comment to the database from form.
    else {
      // XSS protection in $name.
      $name = htmlspecialchars(
        stripslashes(strip_tags(nl2br($form_state->getValue('name'))))
      );
      // XSS protection in $text.
      $text = htmlspecialchars(
        stripslashes(strip_tags(nl2br($form_state->getValue('text'))))
      );
      $query = \Drupal::database()->insert('a_comments');
      $query->fields(
        [
          'name' => $name,
          'email' => "{$form_state->getValue('email')}",
          'phone' => "{$form_state->getValue('phone')}",
          'text' => $text,
          'avatar' => "{$form_state->get('avatarFilename')}",
          'image' => "{$form_state->get('imageFilename')}",
        ]
      );

      $query->execute();
      \Drupal::messenger()->addMessage(
        "Comment '{$form_state->getValue('name')}' with has been created!",
        'status'
      );
    }
  }

  /**
   * A feature that saves files forever.
   * When called cron, they will not be removed.
   *
   * @param $photoName  - element name from form
   * @param $form_state - simple $form_state
   */

  public function setPhotoPermanent($photoName, $form_state) {
    $photoFid = $form_state->getValue($photoName);
    if (!empty($photoFid[0])) {
      $photoFid = $photoFid[0];
      $photo = \Drupal\file\Entity\File::load($photoFid);
      $photo->setPermanent();
      $photo->save();
    }
  }

}
