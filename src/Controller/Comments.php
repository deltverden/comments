<?php


namespace Drupal\comments\Controller;

use Drupal\Core\Controller\ControllerBase;

class Comments extends ControllerBase {

  /**
   * Return all comments from the database and display them on the screen.
   *
   * @return array all comments
   */
  public function get() {

    $addcomment = \Drupal::formBuilder()->getForm(
      'Drupal\comments\Form\AddEdit'
    );

    $comments = [];

    $query = \Drupal::database()->select('a_comments', 'acom');
    $query->fields('acom', []);
    $query->orderBy('date_create', 'DESC');
    $result = $query->execute()->fetchAll();

    foreach ($result as $row) {
      $date = new \DateTime($row->date_create);
      array_push(
        $comments, [
          'id' => $row->id,
          'title' => $row->name,
          'email' => $row->email,
          'phone' => $row->phone,
          'content' => $row->text,
          'date' => $date->format('d-m-Y'),
          'avatar' => $row->avatar,
          'image' => $row->image,
        ]
      );
    }

    $data = [
      'title' => 'Main page',
      'window' => $comments,
    ];

    return [
      '#theme' => 'comments_theme',
      '#addcomment' => $addcomment,
      '#comments' => $data,
    ];
  }

  /**
   * Removes a comment from the database.
   *
   * @param int $id - comment id
   */
  public function delete($id = 0) {
    $query = \Drupal::database()->delete('a_comments');
    $query->condition('id', $id, '=');
    $result = $query->execute();

    if ($result == 1) {
      \Drupal::messenger()->addMessage(
        "Comment with ID: $id has been deleted!", 'status'
      );
    }
    else {
      \Drupal::messenger()->addMessage(
        "Comment with ID: $id is not found!", 'warning'
      );
    }

    header("Location: /" . "comments");
    die();
  }

  /**
   * Edits the comment and saves the changes to the database.
   *
   * @param int $id - comment id
   *
   * @return array form with filled fields
   */
  public function edit($id = 0) {
    $editcomments = \Drupal::formBuilder()->getForm(
      'Drupal\comments\Form\AddEdit', $id
    );

    return [
      '#theme' => 'edit_comments_theme',
      '#editcomments' => $editcomments,
    ];

  }

}
