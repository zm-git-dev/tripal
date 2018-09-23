<?php
namespace Tests\tripal_chado\fields;

use StatonLab\TripalTestSuite\DBTransaction;
use StatonLab\TripalTestSuite\TripalTestCase;

class sbo__relationship_widgetTest extends TripalTestCase {
  // Uncomment to auto start and rollback db transactions per test method.
  use DBTransaction;

  /**
   * Create a fake sbo__relationship_widget field?
   */
  private function initializeWidgetClass($bundle_name, $field_name, $widget_name, $entity_id) {
    $vars = [];
    $vars['widget_class'] = $vars['bundle'] = $vars['entity'] = NULL;

    // First include the appropriate class.
    $widget_class_path = DRUPAL_ROOT . '/' . drupal_get_path('module', 'tripal_chado')
      . '/includes/TripalFields/sbo__relationship/sbo__relationship_widget.inc';
    if ((include_once($widget_class_path)) == TRUE) {

      // Load the bundle and field/instance info.
      $vars['bundle'] = tripal_load_bundle_entity(array('name'=> $bundle_name));
      $vars['field_info'] = field_info_field($field_name);
      $vars['instance_info'] = field_info_instance('TripalEntity', $field_name, $bundle_name);

      // Create an instance of the widget class.
      $vars['widget_class'] = new \sbo__relationship_widget($vars['field_info'], $vars['instance_info']);

      // load an entity to pretend the widget is modifying.
      $vars['entity'] = entity_load('TripalEntity', [$entity_id]);
      $vars['entity'] = $vars['entity'][$entity_id];
    }

    return $vars;
  }

  /**
   * Data Provider: provides entities matching important test cases.
   *
   * Specifically, we will cover three relationship tables, which represent
   * the diversity in the chado schema v1.3:
   *  organism_relationship: subject_id, type_id, object_id,
   *  stock_relationship: subject_id, type_id, object_id, value, rank,
   *  project_relationship: subject_project_id, type_id, object_project_id, rank
   *
   * @returns
   *   Returns an array where each item to be tested has the paramaters 
   *   needed for initializeWidgetClass(). Specfically, $bundle_name, 
   *   $field_name, $widget_name, $entity_id.
   */
  public function provideEntities() {
     $data = [];

     foreach (['organism', 'stock', 'project'] as $base_table) {

       $field_name = 'sbo__relationship';
       $widget_name = 'sbo__relationship_widget';

       // find a bundle which stores it's data in the given base table.
       // This will work on Travis since Tripal creates matching bundles by default.
       // @todo ideally we would create a fake bundle here.
       $bundle_id = db_query("
         SELECT bundle_id 
         FROM chado_bundle b 
         LEFT JOIN tripal_entity e ON e.bundle='bio_data_'||b.bundle_id 
         WHERE data_table=:table AND id IS NOT NULL LIMIT 1",
           array(':table' => $base_table))->fetchField();
       $bundle_name = 'bio_data_'.$bundle_id;

       // Find an entity from the above bundle.
       // @todo find a way to create a fake entity for use here.
       $entity_id = db_query('SELECT id FROM tripal_entity WHERE bundle=:bundle LIMIT 1',
         array(':bundle' => $bundle_name))->fetchField();

       // set variables to guide testing.
       $expect = [
         'has_rank' => TRUE,
         'has_value' => FALSE,
         'subject_key' => 'subject_id',
         'object_key' => 'object_id',
       ];
       if ($base_table == 'organism') { $expect['has_rank'] = FALSE; }
       if ($base_table == 'stock') { $expect['has_value'] = TRUE; }
       if ($base_table == 'project') { 
         $expect['subject_key'] = 'subject_project_id'; 
         $expect['object_key'] = 'object_project_id';
       }

       $data[] = [$bundle_name, $field_name, $widget_name, $entity_id, $expect];
     }
     return $data;
  }

  /**
   * Test that we can initialize the widget properly.
   *
   * @dataProvider provideEntities()
   *
   * @group lacey
   */
  public function testWidgetClassInitialization($bundle_name, $field_name, $widget_name, $entity_id, $expect) {

    // Initialize our variables.
    $vars = $this->initializeWidgetClass($bundle_name, $field_name, $widget_name, $entity_id);

    // Check we have the variables we initialized.
    $this->assertNotEmpty($vars['bundle'], "Could not load the bundle.");
    $this->assertNotEmpty($vars['field_info'], "Could not lookup the field information.");
    $this->assertNotEmpty($vars['instance_info'], "Could not lookup the instance informatiob.");
    $this->assertNotEmpty($vars['widget_class'], "Couldn't create a widget class instance.");
    $this->assertNotEmpty($vars['entity'], "Couldn't load an entity.");

  }

  /**
   * Test the widget Form.
   *
   * @dataProvider provideEntities()
   *
   * @group lacey
   */
  public function testWidgetForm($bundle_name, $field_name, $widget_name, $entity_id, $expect) {

    $vars = $this->initializeWidgetClass($bundle_name, $field_name, $widget_name, $entity_id);
    $base_table = $vars['entity']->chado_table;

    // Stub out a fake $widget object.
    $widget = [
      '#entity_type' => 'TripalEntity',
      '#entity' => $vars['entity'],
      '#bundle' => $vars['bundle'],
      '#field_name' => $field_name,
      '#language' => LANGUAGE_NONE,
      '#field_parents' => [],
      '#columns' => [],
      '#title' => '',
      '#description' => '',
      '#required' => FALSE,
      '#delta' => 0,
      '#weight' => 0, //same as delta.
      'value' => [
        '#type' => 'value',
        '#value' => '',
      ],
      '#field' => $vars['field_info'],
      '#instance' => $vars['instance_info'],
      '#theme' => 'tripal_field_default',
      'element_validate' => ['tripal_field_widget_form_validate']
    ];

    // Stub out the form and form_state.
    $form = [
      '#parents' => [],
      '#entity' => $vars['entity'],
    ];
    $form_state = [
      'build_info' => [
        'args' => [
          0 => NULL,
          1 => $vars['entity']
        ],
        'form_id' => 'tripal_entity_form',
      ],
      'rebuild' => FALSE,
      'rebuild_info' => [],
      'redirect' => NULL,
      'temporary' => [],
      'submitted' => FALSE,
    ];

    // stub out the data for the field.
    $langcode = LANGUAGE_NONE;
    $items = [
      'value' => '',
      'chado-organism_relationship__organism_relationship_id' => '',
      'chado-organism_relationship__subject_id' => '',
      'chado-organism_relationship__object_id' => '',
      'chado-organism_relationship__type_id' => '',
      'chado-organism_relationship__rank' => '',
      'object_name' => '',
      'subject_name' => '',
      'type_name' => '',
    ];
    $delta = 0;

    // Stub out the widget element.
    $element = [
      '#entity_type' => 'TripalEntity',
      '#entity' => $vars['entity'],
      '#bundle' => $bundle_name,
      '#field_name' => $field_name,
      '#language' => LANGUAGE_NONE,
      '#field_parents' => [],
      '#columns' => [],
      '#title' => '',
      '#description' => '',
      '#required' => FALSE,
      '#delta' => 0,
      '#weight' => 0,
    ];

    // Execute the form method.
    $vars['widget_class']->form($widget, $form, $form_state, $langcode, $items, $delta, $element);

    // Check the resulting for array
    $this->assertArrayHasKey('subject_name', $widget, "The form for $bundle_name($base_table) does not have a subject element."); 
    $this->assertArrayHasKey('type_name', $widget, "The form for $bundle_name($base_table) does not have a type element.");
    $this->assertArrayHasKey('object_name', $widget, "The form for $bundle_name($base_table) does not have a object element.");

    // Check the subject/object keys were correctly determined.
    $this->assertEquals($expect['subject_key'], $widget['#subject_id_key'], "The form didn't determine the subject key correctly.");
    $this->assertEquals($expect['object_key'], $widget['#object_id_key'], "The form didn't determine the object key correctly.");

    // If this table has a rank we want to ensure there is a form element to set it.
    if ($expect['has_rank']) {
      $this->assertArrayHasKey('rank', $widget, "The form for $bundle_name($base_table) does not have a rank element and it should.");
    }

    // If this table has a value we want to ensure there is a form element to set it.
    // @todo this test may be problematic b/c all fields have values independent of the db.
    if ($expect['has_value']) { 
      $this->assertArrayHasKey('value', $widget, "The form for $bundle_name($base_table) does not have a value element and it should.");
    } 
  }
}
