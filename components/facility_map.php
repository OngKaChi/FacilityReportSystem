<?php
/**
 * Facility Floor Map Component
 * Drop-in replacement for the "Facility Status Map" section in dashboard.php
 *
 * Requires: $conn (MySQLi connection), $floorStats (array of floor => issue count)
 * 
 * To use in dashboard.php, replace the entire Facility Status Map <div> block with:
 *   <?php include 'components/facility_map.php'; ?>
 *
 * For room-level detail, add a `room` column to your reports table and update
 * the $roomStats query below accordingly.
 */

// ── Floor + Room definitions ────────────────────────────────────────────────
// Each floor has a list of rooms with SVG coordinates (fit inside 660×220 viewBox).
// Add/remove rooms to match your actual building layout.
$floorRooms = [
  'Basement' => [
    ['id'=>'background_0_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'parking_0_1', 'label'=>'Hillside Parking', 'x'=>590, 'y'=>100, 'w'=>60, 'h'=>186],
    ['id'=>'parking_0_2', 'label'=>'Outside Parking', 'x'=>490, 'y'=>8, 'w'=>160, 'h'=>87],
    ['id'=>'staircase_0_1', 'label'=>'Stair1', 'x'=>555, 'y'=>105, 'w'=>20, 'h'=>30],
    ['id'=>'parking_0_3', 'label'=>'Parking Basement 1', 'x'=>50, 'y'=>100, 'w'=>495, 'h'=>35],
    ['id'=>'parking_0_4', 'label'=>'Parking Basement 2', 'x'=>8, 'y'=>140, 'w'=>37, 'h'=>106],
    ['id'=>'parking_0_5', 'label'=>'Parking Basement 3', 'x'=>50, 'y'=>250, 'w'=>455, 'h'=>36],
    ['id'=>'parking_0_6', 'label'=>'Parking Basement 4', 'x'=>510, 'y'=>165, 'w'=>35, 'h'=>80],
    ['id'=>'parking_0_7', 'label'=>'Parking Basement 5', 'x'=>80, 'y'=>200, 'w'=>395, 'h'=>25],
    ['id'=>'parking_0_8', 'label'=>'Parking Basement 6', 'x'=>80, 'y'=>170, 'w'=>395, 'h'=>25],
    ['id'=>'staircase_0_2', 'label'=>'Stair2', 'x'=>140, 'y'=>75, 'w'=>30, 'h'=>20],
    
  ],
  'Level 1' => [
    ['id'=>'background_1_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'parking_1_1', 'label'=>'Downhill Parking', 'x'=>8, 'y'=>45, 'w'=>40, 'h'=>241],
    ['id'=>'parking_1_2', 'label'=>'Uphill Parking', 'x'=>610, 'y'=>45, 'w'=>40, 'h'=>241],
    
    ['id'=>'parking_1_3', 'label'=>'Parking Ground 1', 'x'=>155, 'y'=>10, 'w'=>450, 'h'=>25],
    ['id'=>'staircase_1_1', 'label'=>'Stair1', 'x'=>615, 'y'=>10, 'w'=>20, 'h'=>25],
  
    ['id'=>'parking_1_4', 'label'=>'Parking Ground 2', 'x'=>130, 'y'=>65, 'w'=>450, 'h'=>25],
    ['id'=>'parking_1_5', 'label'=>'Parking Ground 3', 'x'=>105, 'y'=>95, 'w'=>450, 'h'=>25],

    ['id'=>'parking_1_6', 'label'=>'Parking Ground 4', 'x'=>105, 'y'=>150, 'w'=>130, 'h'=>25],
    ['id'=>'lift_1_1', 'label'=>'Lift', 'x'=>240, 'y'=>152, 'w'=>20, 'h'=>15],
    ['id'=>'parking_1_7', 'label'=>'Parking Ground 5', 'x'=>310, 'y'=>150, 'w'=>270, 'h'=>25],

    ['id'=>'room_1_1',      'label'=>'Room', 'x'=>140, 'y'=>180, 'w'=>20, 'h'=>20],
    ['id'=>'room_1_2',      'label'=>'Room', 'x'=>165, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_3',      'label'=>'Room', 'x'=>215, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_4',      'label'=>'Room', 'x'=>310, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_5',      'label'=>'Room', 'x'=>360, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_6',      'label'=>'Room', 'x'=>410, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_7',      'label'=>'Room', 'x'=>460, 'y'=>180, 'w'=>45, 'h'=>20],
    ['id'=>'room_1_8',      'label'=>'Room', 'x'=>510, 'y'=>180, 'w'=>30, 'h'=>20],

    ['id'=>'staircase_1_2', 'label'=>'Stair2', 'x'=>105, 'y'=>180, 'w'=>30, 'h'=>50],
    ['id'=>'staircase_1_3', 'label'=>'Stair3', 'x'=>550, 'y'=>180, 'w'=>30, 'h'=>50],
    
    ['id'=>'parking_1_8', 'label'=>'Parking Ground 6', 'x'=>140, 'y'=>205, 'w'=>405, 'h'=>25],
    
    ['id'=>'parking_1_9', 'label'=>'Parking Ground 7', 'x'=>60, 'y'=>260, 'w'=>540, 'h'=>26],
  ],
  'Level 2' => [
    ['id'=>'background_2_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

      ['id'=>'parking_2_1', 'label'=>'Parking 1', 'x'=>140, 'y'=>8, 'w'=>380, 'h'=>45],
      ['id'=>'parking_2_2', 'label'=>'Parking 2', 'x'=>345, 'y'=>240, 'w'=>175, 'h'=>15],
      ['id'=>'parking_2_3', 'label'=>'Parking 3', 'x'=>230, 'y'=>270, 'w'=>290, 'h'=>16],
      ['id'=>'parking_2_4', 'label'=>'Parking 4',  'x'=>90, 'y'=>8,  'w'=>40, 'h'=>225],
      ['id'=>'parking_2_5', 'label'=>'Parking 5',  'x'=>530, 'y'=>8,  'w'=>40, 'h'=>278],

      // ── TOP ROW ──────────────────────────────────────────────────────────────
      ['id'=>'staircase_2_1', 'label'=>'Stair1', 'x'=>137.4, 'y'=>64.1, 'w'=>18, 'h'=>43.2],
      ['id'=>'room_2_1',      'label'=>'Alkira Restaurant', 'x'=>159, 'y'=>64.1, 'w'=>96, 'h'=>43.2],
      ['id'=>'room_2_2',      'label'=>'Registry Office', 'x'=>258.6, 'y'=>64.1, 'w'=>40.4, 'h'=>43.2],
      ['id'=>'room_2_3',      'label'=>'Finance Office', 'x'=>302.6, 'y'=>64.1, 'w'=>40.4, 'h'=>43.2],
      ['id'=>'room_2_4',      'label'=>'University Placement Office / Sick Bay', 'x'=>346.6, 'y'=>64.1, 'w'=>40.4, 'h'=>43.2],
      ['id'=>'room_2_5',      'label'=>'Artificial Intelligence Laboratory', 'x'=>390, 'y'=>64.1, 'w'=>41.4, 'h'=>43.2],
      ['id'=>'room_2_6',      'label'=>'Cloud Computing Laboratory', 'x'=>434.4, 'y'=>64.1, 'w'=>41.4, 'h'=>43.2],
      ['id'=>'toilets_2_1',   'label'=>'Male Toilet', 'x'=>477.6, 'y'=>64.1, 'w'=>24, 'h'=>43.2],
      ['id'=>'staircase_2_2', 'label'=>'Stair2', 'x'=>504.6, 'y'=>64.1, 'w'=>18, 'h'=>43.2],

      // ── UPPER CORRIDOR ───────────────────────────────────────────────────────
      ['id'=>'corridor_2_1',  'label'=>'', 'x'=>137.4, 'y'=>108.5, 'w'=>385.2, 'h'=>9.6, 'type'=>'corridor'],

      // ── MIDDLE ROW ───────────────────────────────────────────────────────────
      ['id'=>'room_2_7', 'label'=>'Pastry Kitchen', 'x'=>137.4, 'y'=>119.3, 'w'=>42, 'h'=>56.3],
      ['id'=>'cafeteria_2_1', 'label'=>'Cafeteria A', 'x'=>179.6, 'y'=>119.3, 'w'=>60.8, 'h'=>56.4],
      ['id'=>'empty_2_1',     'label'=>'', 'x'=>240.6, 'y'=>119.3, 'w'=>24, 'h'=>56.4, 'disabled'=>true],
      ['id'=>'lift_2_2',      'label'=>'Lift 2', 'x'=>243.6, 'y'=>125.3, 'w'=>23.4, 'h'=>20.4],
      ['id'=>'lift_2_1',      'label'=>'Lift 1', 'x'=>243.6, 'y'=>149.3, 'w'=>23.4, 'h'=>20.4],
      ['id'=>'lobby_2_1',     'label'=>'Lobby', 'x'=>267.4, 'y'=>119.3, 'w'=>74.8, 'h'=>56.4],
      ['id'=>'empty_2_2',     'label'=>'', 'x'=>345.6, 'y'=>119.3, 'w'=>34, 'h'=>56.4, 'disabled'=>true],
      ['id'=>'staircase_2_3', 'label'=>'Middle Staircase', 'x'=>342.6, 'y'=>137.3, 'w'=>36, 'h'=>20.4],
      ['id'=>'cafeteria_2_2', 'label'=>'Cafeteria B', 'x'=>379.6, 'y'=>119.3, 'w'=>51, 'h'=>56.4],
      ['id'=>'room_2_8',      'label'=>'Student Affairs Office', 'x'=>381.6, 'y'=>129.3, 'w'=>13, 'h'=>13],
      ['id'=>'room_2_9',     'label'=>'INTIMA Office', 'x'=>396.6, 'y'=>129.3, 'w'=>23, 'h'=>13],
      ['id'=>'corridor_2_2',  'label'=>'', 'x'=>430.8, 'y'=>118.1, 'w'=>14.4, 'h'=>58.8, 'type'=>'corridor'],
      ['id'=>'room_2_10',      'label'=>'Hotel Mock Room', 'x'=>446.4, 'y'=>119.3, 'w'=>76.2, 'h'=>28.1],
      ['id'=>'room_2_11',      'label'=>'Startup Incubator', 'x'=>446.4, 'y'=>147.8, 'w'=>76.2, 'h'=>13.7],
      ['id'=>'room_2_12',      'label'=>'Bilik MDF', 'x'=>446.4, 'y'=>162, 'w'=>76.2, 'h'=>13.7],

      // ── LOWER CORRIDOR ───────────────────────────────────────────────────────
      ['id'=>'corridor_2_3',  'label'=>'', 'x'=>137.4, 'y'=>176.9, 'w'=>385.2, 'h'=>9.6, 'type'=>'corridor'],

      // ── BOTTOM ROW ───────────────────────────────────────────────────────────
      ['id'=>'staircase_2_4', 'label'=>'Stair3', 'x'=>137.4, 'y'=>187.7, 'w'=>18, 'h'=>43.2],
      ['id'=>'room_2_13',      'label'=>'Cafeteria', 'x'=>159, 'y'=>187.7, 'w'=>35.6, 'h'=>43.2],
      ['id'=>'room_2_14',      'label'=>'Room', 'x'=>198.6, 'y'=>187.7, 'w'=>56.4, 'h'=>43.2],
      ['id'=>'room_2_15',     'label'=>'Reception', 'x'=>258.6, 'y'=>187.7, 'w'=>83.4, 'h'=>43.2],
      ['id'=>'room_2_16',     'label'=>'Chef Executive / Government Liaison Complaince / HDRF Training Provider Office', 'x'=>345.6, 'y'=>187.7, 'w'=>22, 'h'=>43.2],
      ['id'=>'room_2_17',     'label'=>'AFM', 'x'=>370.6, 'y'=>187.7, 'w'=>42, 'h'=>43.2],
      ['id'=>'room_2_18',     'label'=>'Radio Conti Room / Photography Studio', 'x'=>415.6, 'y'=>187.7, 'w'=>28, 'h'=>43.2],
      ['id'=>'room_2_19',     'label'=>'Recording / Broadcast Studio', 'x'=>446.6, 'y'=>187.7, 'w'=>28, 'h'=>43.2],
      ['id'=>'toilets_2_2',   'label'=>'Female Toilet', 'x'=>477.6, 'y'=>187.7, 'w'=>24, 'h'=>43.2],
      ['id'=>'staircase_2_5', 'label'=>'Stair4', 'x'=>504.6, 'y'=>187.7, 'w'=>18, 'h'=>43.2],
  ],

  'Level 3' => [
    ['id'=>'background_3_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'staircase_3_1', 'label'=>'Stair1', 'x'=>8, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'room_3_1',      'label'=>'Aristotle Room', 'x'=>44, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_2',      'label'=>'CADP / SOHOS / Language & MPU Centre / Student Experience Office / International Office', 'x'=>110, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_3',      'label'=>'Network Lab', 'x'=>176, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_4',      'label'=>'Information Technology Services', 'x'=>242, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_5',      'label'=>'IT Lab B', 'x'=>308, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_6',      'label'=>'IT Lab A', 'x'=>374, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_7',      'label'=>'Thermofluid Lab', 'x'=>440, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_8',      'label'=>'School of Business', 'x'=>506, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'toilets_3_1',   'label'=>'Male Toilet', 'x'=>575, 'y'=>8, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_3_2', 'label'=>'Stair2', 'x'=>620, 'y'=>8, 'w'=>30, 'h'=>72],

    // Upper corridor
    ['id'=>'corridor_3_1', 'label'=>'', 'x'=>8, 'y'=>82, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    // Middle row
    ['id'=>'room_3_9',      'label'=>'CEPS', 'x'=>8, 'y'=>100, 'w'=>74, 'h'=>46],
    ['id'=>'room_3_10',     'label'=>'Solid Mechanics Lab', 'x'=>8, 'y'=>148, 'w'=>74, 'h'=>46],
    ['id'=>'corridor_3_2',  'label'=>'', 'x'=>84, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'empty_3_1',     'label'=>'', 'x'=>110, 'y'=>100, 'w'=>110, 'h'=>94, 'disabled'=>true],
    ['id'=>'lift_3_2',      'label'=>'Lift 2', 'x'=>185, 'y'=>110, 'w'=>39, 'h'=>34],
    ['id'=>'lift_3_1',      'label'=>'Lift 1', 'x'=>185, 'y'=>150, 'w'=>39, 'h'=>34],
    ['id'=>'cubical_area',  'label'=>'Cubical Area', 'x'=>235, 'y'=>102, 'w'=>105, 'h'=>90],
    ['id'=>'empty_3_2',     'label'=>'', 'x'=>355, 'y'=>100, 'w'=>140, 'h'=>94, 'disabled'=>true],
    ['id'=>'staircase_3_3', 'label'=>'Middle Staircase', 'x'=>350, 'y'=>130, 'w'=>60, 'h'=>34],
    ['id'=>'corridor_3_3',  'label'=>'', 'x'=>497, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'room_3_11',     'label'=>'Corporate Training Room 1', 'x'=>523, 'y'=>100, 'w'=>127, 'h'=>45],
    ['id'=>'room_3_12',     'label'=>'Human Resource Office', 'x'=>523, 'y'=>117, 'w'=>55, 'h'=>28],
    ['id'=>'room_3_13',     'label'=>'Career Services Office', 'x'=>523, 'y'=>147, 'w'=>70, 'h'=>47],
    ['id'=>'room_3_14',     'label'=>'Convenience Store', 'x'=>595, 'y'=>147, 'w'=>55, 'h'=>47],

    // Lower corridor
    ['id'=>'corridor_3_4', 'label'=>'', 'x'=>8, 'y'=>196, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    ['id'=>'staircase_3_4', 'label'=>'Stair3', 'x'=>8, 'y'=>214, 'w'=>30, 'h'=>72],
    ['id'=>'room_3_15',     'label'=>'Psychology Lab / CAE / CCL', 'x'=>40, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_16',     'label'=>'AAC / SOE / SOC', 'x'=>103, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_3_17',     'label'=>'Lecturer Office', 'x'=>166, 'y'=>214, 'w'=>329, 'h'=>72],
    ['id'=>'room_3_18',     'label'=>'Academic Office', 'x'=>497, 'y'=>214, 'w'=>73, 'h'=>72],
    ['id'=>'toilets_3_2',   'label'=>'Female Toilet', 'x'=>575, 'y'=>214, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_3_5', 'label'=>'Stair4', 'x'=>620, 'y'=>214, 'w'=>30, 'h'=>72],
  ],
  'Level 4' => [
    ['id'=>'background_4_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'staircase_4_1', 'label'=>'Stair1', 'x'=>8, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'room_4_1',      'label'=>'LR401A', 'x'=>44, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_2',      'label'=>'LR401', 'x'=>110, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_3',      'label'=>'LR402', 'x'=>176, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_4',      'label'=>'LR403', 'x'=>242, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_5',      'label'=>'LR404', 'x'=>308, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_6',      'label'=>'LR405', 'x'=>374, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_7',      'label'=>'LR406', 'x'=>440, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_8',      'label'=>'LR407', 'x'=>506, 'y'=>8, 'w'=>61, 'h'=>72],
    ['id'=>'toilets_4_1',   'label'=>'Male Toilet', 'x'=>575, 'y'=>8, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_4_2', 'label'=>'Stair2', 'x'=>620, 'y'=>8, 'w'=>30, 'h'=>72],

    // Upper corridor
    ['id'=>'corridor_4_1', 'label'=>'', 'x'=>8, 'y'=>82, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    // Middle row
    ['id'=>'room_4_9',      'label'=>'Design Simulation Lab', 'x'=>8, 'y'=>100, 'w'=>74, 'h'=>46],
    ['id'=>'room_4_10',      'label'=>'Science Lab Store', 'x'=>8, 'y'=>148, 'w'=>74, 'h'=>46],
    ['id'=>'corridor_4_2',  'label'=>'', 'x'=>84, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'empty_4_1',     'label'=>'', 'x'=>110, 'y'=>100, 'w'=>110, 'h'=>94, 'disabled'=>true],
    ['id'=>'lift_4_2',      'label'=>'Lift 2', 'x'=>185, 'y'=>110, 'w'=>39, 'h'=>34],
    ['id'=>'lift_4_1',      'label'=>'Lift 1', 'x'=>185, 'y'=>150, 'w'=>39, 'h'=>34],
    ['id'=>'study_area',  'label'=>'Study Area', 'x'=>235, 'y'=>102, 'w'=>105, 'h'=>90],
    ['id'=>'empty_4_2',     'label'=>'', 'x'=>355, 'y'=>100, 'w'=>140, 'h'=>94, 'disabled'=>true],
    ['id'=>'staircase_4_3', 'label'=>'Main Stair', 'x'=>350, 'y'=>130, 'w'=>60, 'h'=>34],
    ['id'=>'corridor_4_3',  'label'=>'', 'x'=>497, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'room_4_11',     'label'=>'Project Lab 2', 'x'=>523, 'y'=>100, 'w'=>127, 'h'=>46],
    ['id'=>'room_4_12',     'label'=>'Teletronic Lab', 'x'=>523, 'y'=>148, 'w'=>127, 'h'=>46],

    // Lower corridor
    ['id'=>'corridor_4_4', 'label'=>'', 'x'=>8, 'y'=>196, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    ['id'=>'staircase_4_4', 'label'=>'Stair3', 'x'=>8, 'y'=>214, 'w'=>30, 'h'=>72],
    ['id'=>'room_4_13',     'label'=>'Chemistry Lab 1', 'x'=>44, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_14',     'label'=>'Chemistry Lab 2', 'x'=>110, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_15',     'label'=>'Science Preparation Lab', 'x'=>176, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_16',     'label'=>'Physics Lab', 'x'=>242, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_17',     'label'=>'Biology Lab', 'x'=>308, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_18',     'label'=>'Motorola Solutions Embedded Lab', 'x'=>374, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_19',     'label'=>'Electrical Lab', 'x'=>440, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'room_4_20',     'label'=>'Project Lab 1', 'x'=>506, 'y'=>214, 'w'=>61, 'h'=>72],
    ['id'=>'toilets_4_2',   'label'=>'Female Toilet', 'x'=>575, 'y'=>214, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_4_5', 'label'=>'Stair4', 'x'=>620, 'y'=>214, 'w'=>30, 'h'=>72],
  ],
  'Level 5' => [
    ['id'=>'background_5_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'staircase_5_1', 'label'=>'Stair1', 'x'=>8, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'room_5_1',      'label'=>'LR501', 'x'=>40, 'y'=>8, 'w'=>60, 'h'=>72],
    ['id'=>'room_5_2',      'label'=>'LR502', 'x'=>102, 'y'=>8, 'w'=>60, 'h'=>72],
    ['id'=>'room_5_3',      'label'=>'LR503', 'x'=>164, 'y'=>8, 'w'=>60, 'h'=>72],
    ['id'=>'room_5_4',      'label'=>'LR504', 'x'=>226, 'y'=>8, 'w'=>65, 'h'=>72],
    ['id'=>'room_5_5',      'label'=>'LR505', 'x'=>296, 'y'=>8, 'w'=>65, 'h'=>72],
    ['id'=>'room_5_6',      'label'=>'LR506', 'x'=>366, 'y'=>8, 'w'=>65, 'h'=>72],
    ['id'=>'room_5_7',      'label'=>'LR507', 'x'=>436, 'y'=>8, 'w'=>65, 'h'=>72],
    ['id'=>'room_5_8',      'label'=>'LR508', 'x'=>506, 'y'=>8, 'w'=>65, 'h'=>72],
    ['id'=>'toilets_5_1',   'label'=>'Male Toilet', 'x'=>575, 'y'=>8, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_5_2', 'label'=>'Stair2', 'x'=>620, 'y'=>8, 'w'=>30, 'h'=>72],

    // Upper corridor
    ['id'=>'corridor_5_1', 'label'=>'', 'x'=>8, 'y'=>82, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    // Middle row
    ['id'=>'room_5_9',      'label'=>'Mock Reception', 'x'=>8, 'y'=>100, 'w'=>74, 'h'=>46],
    ['id'=>'room_5_10',      'label'=>'iMAC Lab', 'x'=>8, 'y'=>148, 'w'=>74, 'h'=>46],
    ['id'=>'corridor_5_2',  'label'=>'', 'x'=>84, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'empty_5_1',     'label'=>'', 'x'=>110, 'y'=>100, 'w'=>110, 'h'=>94, 'disabled'=>true],
    ['id'=>'lift_5_2',      'label'=>'Lift 2', 'x'=>185, 'y'=>110, 'w'=>39, 'h'=>34],
    ['id'=>'lift_5_1',      'label'=>'Lift 1', 'x'=>185, 'y'=>150, 'w'=>39, 'h'=>34],
    ['id'=>'empty_5_2',     'label'=>'', 'x'=>355, 'y'=>100, 'w'=>140, 'h'=>94, 'disabled'=>true],
    ['id'=>'staircase_5_3', 'label'=>'Middle Staircase', 'x'=>350, 'y'=>130, 'w'=>60, 'h'=>34],
    ['id'=>'corridor_5_3',  'label'=>'', 'x'=>497, 'y'=>98, 'w'=>24, 'h'=>98, 'type'=>'corridor'],
    ['id'=>'room_5_10',     'label'=>'STEM Lab', 'x'=>523, 'y'=>100, 'w'=>63, 'h'=>46],
    ['id'=>'room_5_11',     'label'=>'LR509B', 'x'=>587, 'y'=>100, 'w'=>63, 'h'=>46],
    ['id'=>'room_5_12',     'label'=>'LR501A', 'x'=>523, 'y'=>148, 'w'=>63, 'h'=>46],
    ['id'=>'room_5_13',     'label'=>'LR501B', 'x'=>587, 'y'=>148, 'w'=>63, 'h'=>46],

    // Lower corridor
    ['id'=>'corridor_5_4', 'label'=>'', 'x'=>8, 'y'=>196, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    ['id'=>'staircase_5_4', 'label'=>'Stair3', 'x'=>8, 'y'=>214, 'w'=>30, 'h'=>72],
    ['id'=>'room_5_14',     'label'=>'LR514', 'x'=>40, 'y'=>214, 'w'=>60, 'h'=>72],
    ['id'=>'room_5_15',      'label'=>'LR513', 'x'=>102, 'y'=>214, 'w'=>60, 'h'=>72],
    ['id'=>'room_5_16',      'label'=>'LR512', 'x'=>164, 'y'=>214, 'w'=>85, 'h'=>72],
    ['id'=>'room_5_17',      'label'=>'Maintenance', 'x'=>251, 'y'=>214, 'w'=>55, 'h'=>72],
    ['id'=>'room_5_18',     'label'=>'Lecture Theater', 'x'=>308, 'y'=>214, 'w'=>123, 'h'=>72],
    ['id'=>'room_5_19',      'label'=>'LR511', 'x'=>433, 'y'=>214, 'w'=>65, 'h'=>72],
    ['id'=>'room_5_20',      'label'=>'Sarau Lelaki', 'x'=>501, 'y'=>214, 'w'=>34, 'h'=>72],
    ['id'=>'room_5_21',      'label'=>'Sarau Perempuan', 'x'=>538, 'y'=>214, 'w'=>34, 'h'=>72],
    ['id'=>'toilets_5_2',   'label'=>'Female Toilet', 'x'=>575, 'y'=>214, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_5_5', 'label'=>'Stair4', 'x'=>620, 'y'=>214, 'w'=>30, 'h'=>72],
  ],
  'Level 6' => [
    ['id'=>'background_6_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],
    // Top row
    ['id'=>'staircase_6_1', 'label'=>'Stair1', 'x'=>8, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'room_6_1',      'label'=>'Exam Room', 'x'=>98, 'y'=>8, 'w'=>110, 'h'=>72],
    ['id'=>'library_6_1',   'label'=>'Discussion Room', 'x'=>44, 'y'=>8, 'w'=>160, 'h'=>72],
    ['id'=>'library_6_2',   'label'=>'Library', 'x'=>210, 'y'=>8, 'w'=>315, 'h'=>72],
    ['id'=>'toilets_6_1',   'label'=>'Female Toilet', 'x'=>530, 'y'=>8, 'w'=>40, 'h'=>72],
    ['id'=>'toilets_6_2',   'label'=>'Male Toilet', 'x'=>575, 'y'=>8, 'w'=>40, 'h'=>72],
    ['id'=>'staircase_6_2', 'label'=>'Stair2', 'x'=>620, 'y'=>8, 'w'=>30, 'h'=>72],

    // Upper corridor
    ['id'=>'corridor_6_1', 'label'=>'', 'x'=>8, 'y'=>82, 'w'=>642, 'h'=>16, 'type'=>'corridor'],

    // Middle row
    ['id'=>'room_6_2',      'label'=>'LR604', 'x'=>8, 'y'=>100, 'w'=>100, 'h'=>46],
    ['id'=>'room_6_3',      'label'=>'LR603', 'x'=>8, 'y'=>148, 'w'=>100, 'h'=>46],
    ['id'=>'empty_6_1',     'label'=>'', 'x'=>110, 'y'=>100, 'w'=>110, 'h'=>94, 'disabled'=>true],
    ['id'=>'lift_6_2',      'label'=>'Lift 2', 'x'=>185, 'y'=>110, 'w'=>39, 'h'=>34],
    ['id'=>'lift_6_1',      'label'=>'Lift 1', 'x'=>185, 'y'=>150, 'w'=>39, 'h'=>34],
    ['id'=>'empty_6_2',     'label'=>'', 'x'=>355, 'y'=>100, 'w'=>140, 'h'=>94, 'disabled'=>true],
    ['id'=>'staircase_6_3', 'label'=>'Middle Staircase', 'x'=>350, 'y'=>130, 'w'=>60, 'h'=>34],
    ['id'=>'room_6_4',     'label'=>'Multi Purpose Hall', 'x'=>497, 'y'=>100, 'w'=>153, 'h'=>94],
    

    // Lower corridor
    ['id'=>'corridor_6_2', 'label'=>'', 'x'=>8, 'y'=>196, 'w'=>642, 'h'=>16, 'type'=>'corridor'],
    ['id'=>'store_6_1',      'label'=>'Store', 'x'=>255, 'y'=>175, 'w'=>75, 'h'=>40],

    // Bottom row 
    ['id'=>'staircase_6_4', 'label'=>'Stair3', 'x'=>8, 'y'=>214, 'w'=>30, 'h'=>72],
    ['id'=>'room_6_5',      'label'=>'LR602', 'x'=>44, 'y'=>214, 'w'=>50, 'h'=>72],
    ['id'=>'room_6_6',      'label'=>'LR601', 'x'=>98, 'y'=>214, 'w'=>50, 'h'=>72],
    ['id'=>'room_6_7',      'label'=>'Student Counselling Service', 'x'=>152, 'y'=>214, 'w'=>50, 'h'=>72],
    ['id'=>'room_6_8',      'label'=>'Lecture Theater', 'x'=>204, 'y'=>214, 'w'=>227, 'h'=>72],
    ['id'=>'room_6_9',      'label'=>'LR605', 'x'=>433, 'y'=>214, 'w'=>65, 'h'=>72],
    ['id'=>'room_6_10',      'label'=>'LR606', 'x'=>500, 'y'=>214, 'w'=>114, 'h'=>72],
    ['id'=>'staircase_6_5', 'label'=>'Stair4', 'x'=>620, 'y'=>214, 'w'=>30, 'h'=>72],
  ],
  'Rooftop' => [
    ['id'=>'background_7_1', 'label'=>'', 'x'=>8, 'y'=>8, 'w'=>642, 'h'=>278, 'disabled'=>true],

    ['id'=>'staircase_7_1', 'label'=>'Stair1', 'x'=>8, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'rooftop_7_1', 'label'=>'Rooftop Outside Seat 1', 'x'=>265, 'y'=>8, 'w'=>90, 'h'=>70],
    ['id'=>'rooftop_7_2', 'label'=>'Rooftop Outside Seat 2', 'x'=>75, 'y'=>8, 'w'=>90, 'h'=>70],
    ['id'=>'rooftop_7_3', 'label'=>'Rooftop Inside Seat 1', 'x'=>265, 'y'=>221, 'w'=>110, 'h'=>65],
    ['id'=>'rooftop_7_4', 'label'=>'Rooftop Inside Seat 2', 'x'=>255, 'y'=>155, 'w'=>130, 'h'=>65],
    ['id'=>'rooftop_7_5', 'label'=>'Rooftop Barbeque Area', 'x'=>520, 'y'=>8, 'w'=>95, 'h'=>70],
    ['id'=>'staircase_7_2', 'label'=>'Stair2', 'x'=>620, 'y'=>8, 'w'=>30, 'h'=>72],
    ['id'=>'staircase_7_3', 'label'=>'Middle Staircase', 'x'=>380, 'y'=>235, 'w'=>45, 'h'=>25],
  ],
];

// Fetch custom room labels from database
$dbRoomLabels = $conn->query("SELECT floor, room_id, label FROM facility_rooms");
if ($dbRoomLabels) {
    while ($row = $dbRoomLabels->fetch_assoc()) {
        if (isset($floorRooms[$row['floor']])) {
            foreach ($floorRooms[$row['floor']] as &$room) {
                if ($room['id'] === $row['room_id']) { $room['label'] = $row['label']; }
            }
        }
    }
    unset($room); // Unset the reference to prevent the bug.
}

// ── Room-level issue counts from DB ─────────────────────────────────────────
// If your reports table has a `room` column, use this query.
// Otherwise, all issues will be distributed to the first room on each floor
// (just for display — replace with real data as needed).
$roomStats = [];

// Build a reverse map from label to room_id for efficient lookup

$roomQuery = $conn->query("SELECT floor, location, COUNT(*) as count FROM reports WHERE status != 'Resolved' GROUP BY floor, location");
while ($r = $roomQuery->fetch_assoc()) {
    $floor = $r['floor'];
    $locationLabel = $r['location'];
    $count = (int)$r['count'];

    if (isset($floorRooms[$floor])) {
        // Find all rooms that match the label on that floor
        $matchingRooms = array_filter($floorRooms[$floor], function($room) use ($locationLabel) {
            return isset($room['label']) && $room['label'] === $locationLabel;
        });

        // Distribute the count to all matching rooms.
        // In most cases, there will be only one. If there are duplicates, they all get the count.
        foreach ($matchingRooms as $room) {
            $roomId = $room['id'];
            // Use += to accumulate counts if a room_id somehow gets processed twice.
            $roomStats[$floor][$roomId] = ($roomStats[$floor][$roomId] ?? 0) + $count;
        }
    }
}

// Encode data for JS
$floorRoomsJson = json_encode($floorRooms);
$roomStatsJson  = json_encode($roomStats);
$floorStatsJson = json_encode($floorStats);
$floorListJson  = json_encode(array_keys($floorRooms));
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$isUser = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'user';
?>

<!-- ═══════════════════════════════════════════════════════════════════════
     FACILITY STATUS MAP  —  SVG floor plan with room-level issue pins
     Drop this block in place of the existing "Facility Status Map" div
     ═══════════════════════════════════════════════════════════════════════ -->
<style>
  .fm-room-group {
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s ease;
    transform-box: fill-box;
    transform-origin: center;
    cursor: pointer;
  }

  .fm-room-group rect {
    transition: stroke 0.2s ease, fill 0.2s ease, filter 0.2s ease;
    filter: drop-shadow(0 2px 4px rgba(15, 23, 42, 0.08));
  }

  .fm-room-group:hover,
  .fm-room-group.selected {
    transform: scale(1.01);
    filter: drop-shadow(0 12px 18px rgba(15, 23, 42, 0.18));
  }

  .fm-floor-btn {
    min-width: 110px;
    transition: all 0.2s ease;
  }

  .fm-floor-btn.active {
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.22);
    transform: translateY(-1px);
  }

  .fm-floor-btn .count-badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
  }

  .fm-map-summary {
    border: 1px solid rgba(148, 163, 184, 0.2);
  }

  .fm-map-scroll {
    overflow-x: hidden;
  }

  @media (max-width: 640px) {
    .fm-map-scroll {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior-x: contain;
      scrollbar-width: thin;
    }

    .fm-map-scroll svg {
      min-width: 660px;
    }
  }
</style>

<div class="bg-white dark:bg-slate-800 dark:border dark:border-slate-700 rounded-xl shadow-lg p-6 mb-8 transition-colors duration-300">

  <!-- Header -->
  <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-5 gap-4">
    <div>
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Facility Status Map</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400">Click a floor to see room conditions and active issues</p>
    </div>

    <div class="flex flex-wrap gap-2 text-xs font-medium dark:text-gray-300">
      <span class="fm-map-summary flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300 px-2.5 py-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> Clear</span>
      <span class="fm-map-summary flex items-center gap-2 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300 px-2.5 py-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span> 1–2 Issues</span>
      <span class="fm-map-summary flex items-center gap-2 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-300 px-2.5 py-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> 3+ Issues</span>
    </div>
  </div>

  <!-- Floor selector buttons -->
  <div class="flex flex-wrap gap-2 mb-4" id="fm-floor-btns"></div>

  <!-- SVG canvas -->
  <div class="fm-map-scroll relative bg-gray-50 dark:bg-slate-900/50 border border-gray-200 dark:border-slate-700 rounded-xl shadow-inner" style="min-height:295px;">
    <svg id="fm-svg" viewBox="0 0 660 295" width="100%" style="display:block;" role="img" aria-label="Interactive facility floor map"></svg>

    <!-- Tooltip (hidden by default, shown on room hover) -->
    <div id="fm-tooltip"
         class="absolute pointer-events-none hidden bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-lg px-3 py-2 text-xs text-gray-800 dark:text-gray-200 z-20 min-w-max">
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <!-- Room Label Editor Modal (Admin Only) -->
  <div id="fm-edit-modal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60] p-4 transition-all">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl border dark:border-slate-700">
      <h4 class="text-lg font-bold mb-1 dark:text-white">Edit Room Label</h4>
      <p class="text-xs text-gray-500 mb-4">Change the display name for this room on the map.</p>
      <input type="text" id="fm-new-label" class="w-full p-3 border dark:border-slate-600 rounded-xl mb-4 dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
      <div class="flex justify-end gap-3">
        <button onclick="closeRoomEdit()" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Cancel</button>
        <button onclick="saveRoomLabel()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20">Update Label</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Active issue pills for current floor -->
  <div id="fm-issue-pills" class="flex flex-wrap gap-2 mt-4"></div>

</div>
<!-- ═══════════════════════════════════════════════════════════════════════ -->


<script>
(function () {
  // ── Data injected from PHP ──────────────────────────────────────────────
  const FLOOR_ROOMS  = <?php echo $floorRoomsJson; ?>;
  const ROOM_STATS   = <?php echo $roomStatsJson; ?>;
  const FLOOR_STATS  = <?php echo $floorStatsJson; ?>;
  const FLOOR_ORDER  = <?php echo $floorListJson; ?>;
  const IS_ADMIN     = <?php echo $isAdmin ? 'true' : 'false'; ?>;
  const IS_USER      = <?php echo $isUser ? 'true' : 'false'; ?>;

  // ── DOM refs ────────────────────────────────────────────────────────────
  const svg       = document.getElementById('fm-svg');
  const tooltip   = document.getElementById('fm-tooltip');
  const btnWrap   = document.getElementById('fm-floor-btns');
  const pillWrap  = document.getElementById('fm-issue-pills');
  <?php if ($isAdmin): ?>
  const editModal  = document.getElementById('fm-edit-modal');
  const labelInput = document.getElementById('fm-new-label');
  <?php endif; ?>

  let editingRoom = null;

  let currentFloor = 'Level 2'; // Default to Ground Floor

  // ── Helpers ─────────────────────────────────────────────────────────────
  function issueCount(floor)  { return FLOOR_STATS[floor] || 0; }
  function roomCount(floor, roomId) { return (ROOM_STATS[floor] && ROOM_STATS[floor][roomId]) || 0; }

  function statusColor(n, isDark) {
    if (n === 0)  return {
      dot: '#10b981',
      fill: isDark ? '#123a35' : '#d9f9ee',
      stroke: '#0f766e',
      text: isDark ? '#d1fae5' : '#115e59',
      soft: isDark ? '#042f2e' : '#ecfdf5'
    };
    if (n <= 2)   return {
      dot: '#d97706',
      fill: isDark ? '#3f2d0d' : '#fef3c7',
      stroke: '#b45309',
      text: isDark ? '#fde68a' : '#92400e',
      soft: isDark ? '#451a03' : '#fff7ed'
    };
    return {
      dot: '#dc2626',
      fill: isDark ? '#4a1014' : '#fee2e2',
      stroke: '#b91c1c',
      text: isDark ? '#fecaca' : '#991b1b',
      soft: isDark ? '#3b0a0a' : '#fff1f2'
    };
  }

  function roomSeverityLabel(n) {
    if (n === 0) return 'Clear';
    if (n <= 2) return 'Monitor';
    return 'Critical';
  }

  function svgEl(tag, attrs) {
    const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    return el;
  }

  // ── Build floor selector buttons ────────────────────────────────────────
  FLOOR_ORDER.forEach(floor => {
    const isDark = document.documentElement.classList.contains('dark');
    const n = issueCount(floor);
    const col = statusColor(n, isDark);
    const btn = document.createElement('button');

    btn.dataset.floor = floor;
    btn.className = 'fm-floor-btn floor-btn rounded-2xl border px-3 py-2 text-left flex items-center justify-between gap-3 text-xs font-medium';
    btn.innerHTML = `
      <span class="flex flex-col items-start">
        <span class="font-semibold">${floor}</span>
        <span class="text-[10px] opacity-75">${n} active</span>
      </span>
      <span class="count-badge rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background:${col.dot};">${n}</span>
    `;

    btn.addEventListener('click', () => selectFloor(floor));
    btnWrap.appendChild(btn);
  });

  function updateBtnStyles() {
    btnWrap.querySelectorAll('.floor-btn').forEach(btn => {
      const active = btn.dataset.floor === currentFloor;
      btn.className = 'fm-floor-btn floor-btn rounded-2xl border px-3 py-2 text-left flex items-center justify-between gap-3 text-xs font-medium transition-all duration-200 '
        + (active
          ? 'active bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/20'
          : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border-gray-300 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700');

      const countBadge = btn.querySelector('.count-badge');
      if (countBadge) {
        const n = issueCount(btn.dataset.floor);
        const col = statusColor(n, document.documentElement.classList.contains('dark'));
        countBadge.style.background = col.dot;
      }
    });
  }

  // ── Select a floor and re-render ─────────────────────────────────────────
  function selectFloor(floor) {
    currentFloor = floor;
    updateBtnStyles();
    renderFloor();
  }

  function renderRoom(room, n, col) {
    const g = svgEl('g', { class: 'fm-room-group' });
    g.appendChild(svgEl('rect', {
      x: room.x, y: room.y, width: room.w, height: room.h, rx: 6,
      fill: col.fill, stroke: col.stroke, 'stroke-width': n > 0 ? '1.8' : '1.1'
    }));

    if (room.label) {
      const words = room.label.split(' ');
      const wrap = words.length > 1 && (room.label.length > 10 || room.w < 70);
      const midpoint = Math.ceil(words.length / 2);
      const lines = wrap
        ? [words.slice(0, midpoint).join(' '), words.slice(midpoint).join(' ')]
        : [room.label];

      lines.forEach((text, i) => {
        const availableWidth = room.w - 6;
        const fontSize = 6.7;
        const offset = lines.length > 1 ? (i ? fontSize * 0.7 : -fontSize * 0.7) : 0;
        const label = svgEl('text', {
          x: room.x + room.w / 2, y: room.y + room.h / 2 + offset,
          'text-anchor': 'middle', 'dominant-baseline': 'middle',
          'font-size': fontSize, 'font-weight': '500', fill: col.text,
          'pointer-events': 'none', class: 'fm-room-label'
        });
        label.textContent = text;
        label.dataset.maxWidth = availableWidth;
        g.appendChild(label);
      });
    }

    if (n > 0) {
      const px = room.x + room.w - 10;
      const py = room.y + 10;
      g.appendChild(svgEl('circle', { cx: px, cy: py, r: 9, fill: col.stroke }));

      const count = svgEl('text', {
        x: px, y: py, 'text-anchor': 'middle', 'dominant-baseline': 'middle',
        'font-size': '8', 'font-weight': '700', fill: 'white', 'pointer-events': 'none'
      });
      count.textContent = n;
      g.appendChild(count);

      const pill = document.createElement('span');
      pill.className = 'inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium';
      pill.style.background = col.fill;
      pill.style.color = col.stroke;
      pill.style.border = `1px solid ${col.stroke}`;
      pill.innerHTML = `<span style="font-size:8px;">●</span> ${room.label || room.id} — ${n} issue${n > 1 ? 's' : ''}`;
      pillWrap.appendChild(pill);
    }

    const isDisabled = room.disabled === true;
    g.style.cursor = isDisabled ? 'not-allowed' : 'pointer';
    g.addEventListener('mouseenter', () => {
      if (isDisabled || (n === 0 && !IS_ADMIN)) return;
      tooltip.innerHTML = `
        <div class="flex items-center justify-between gap-3 mb-1">
          <strong class="block text-sm font-semibold">${room.label || room.id}</strong>
          <span class="text-[10px] uppercase tracking-[0.18em] px-1.5 py-0.5 rounded-full font-bold" style="background:${col.soft}; color:${col.stroke};">${roomSeverityLabel(n)}</span>
        </div>
        ${n > 0 ? `<div class="text-xs" style="color:${col.stroke};">${n} active issue${n > 1 ? 's' : ''}</div>` : ''}
        ${IS_ADMIN && room.id ? `<div class="mt-1 text-[10px] text-blue-500 font-bold uppercase tracking-wider">Click to Edit Label</div>` : ''}
        ${IS_USER && room.id ? `<div class="mt-1 text-[10px] text-blue-500 font-bold uppercase tracking-wider">Click to Report Issue</div>` : ''}
      `;
      tooltip.classList.remove('hidden');
    });

    g.addEventListener('mousemove', e => {
      const parentRect = svg.parentElement.getBoundingClientRect();
      tooltip.style.left = e.clientX - parentRect.left + 12 + 'px';
      tooltip.style.top = e.clientY - parentRect.top + 12 + 'px';
    });

    g.addEventListener('mouseleave', () => tooltip.classList.add('hidden'));

    g.addEventListener('click', e => {
      e.stopPropagation();
      if (isDisabled) return;
      svg.querySelectorAll('.fm-room-group').forEach(el => el.classList.remove('selected'));
      g.classList.add('selected');
      svg.appendChild(g);
      if (IS_ADMIN && room.id && typeof openRoomEdit === 'function') {
        editingRoom = { floor: currentFloor, id: room.id, currentLabel: room.label };
        openRoomEdit();
      } else if (IS_USER && room.id) {
        const reportFloor = document.getElementById('floor');
        const reportRoom = document.getElementById('location');
        const reportRoomSelect = document.getElementById('customRoomDropdown');

        if (!reportFloor || !reportRoom || !reportRoomSelect) return;

        reportFloor.value = currentFloor;
        reportFloor.dispatchEvent(new Event('change'));
        reportRoomSelect.value = room.label || room.id;
        reportRoomSelect.dispatchEvent(new Event('change'));
        if (typeof openReportModal === 'function') openReportModal();
      }
    });
    svg.appendChild(g);

    g.querySelectorAll('.fm-room-label').forEach(label => {
      const maxWidth = Number(label.dataset.maxWidth);
      if (label.getComputedTextLength() <= maxWidth) return;

      const characters = Array.from(label.textContent);
      while (characters.length > 0) {
        characters.pop();
        label.textContent = `${characters.join('').trimEnd()}...`;
        if (label.getComputedTextLength() <= maxWidth) return;
      }

      const ellipsisWidth = label.getComputedTextLength();
      if (ellipsisWidth > maxWidth) {
        label.setAttribute('font-size', Math.max(5.5, 9 * maxWidth / ellipsisWidth));
      }
    });
  }

  function renderFloor() {
    svg.innerHTML = '';
    pillWrap.innerHTML = '';
    const isDark = document.documentElement.classList.contains('dark');

    (FLOOR_ROOMS[currentFloor] || []).forEach(room => {
      const count = roomCount(currentFloor, room.id || room.label);
      renderRoom(room, count, statusColor(count, isDark));
    });

    if (!pillWrap.children.length) {
      const empty = document.createElement('span');
      empty.className = 'text-xs text-gray-400 dark:text-gray-500 py-1';
      empty.textContent = 'No active issues on this floor.';
      pillWrap.appendChild(empty);
    }
  }

  // ── Admin Edit UI Functions ─────────────────────────────────────────────
  <?php if ($isAdmin): ?>
  window.openRoomEdit = function() {
    labelInput.value = editingRoom.currentLabel;
    editModal.classList.remove('hidden');
    editModal.classList.add('flex');
    labelInput.focus();
  };

  window.closeRoomEdit = function() {
    editModal.classList.add('hidden');
    editModal.classList.remove('flex');
  };

  window.saveRoomLabel = function() {
    const newLabel = labelInput.value.trim();
    if (!newLabel) return;

    const body = new URLSearchParams({
      floor: editingRoom.floor,
      room_id: editingRoom.id,
      label: newLabel
    }).toString();

    fetch('update_room_label.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) location.reload();
    });
  };
  <?php endif; ?>

  // Click background to deselect
  svg.addEventListener('click', () => {
    svg.querySelectorAll('.fm-room-group').forEach(el => el.classList.remove('selected'));
  });

  // Observe changes to the html tag for dark mode to re-render map
  const themeObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === 'class') {
        selectFloor(currentFloor);
      }
    });
  });
  themeObserver.observe(document.documentElement, { attributes: true });

  updateBtnStyles();
  renderFloor();

})();
</script>