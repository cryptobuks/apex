<?php
declare(strict_types = 1);

namespace apex\core;

use apex\app;
use apex\services\db;
use apex\services\debug;
use apex\services\template;
use apex\app\utils\forms;
use apex\app\sys\encrypt;
use apex\app\exceptions\MiscException;


/**
 * Handles all functions relating to administrator accounts, including create, 
 * delete, load, update security questions, etc. 
 */
class admin
{




    // Properties
    private $app;
    private $encrypt;
    private $forms;
    private $admin_id = 0;

/**
 * Initiates the class, and accepts an optional ID# of administrator. 
 *
 * @param int $admin_id Optional ID# of administrator to manage / update / delete.
 */
public function __construct(app $app, encrypt $encrypt, forms $forms, int $id = 0)
{ 
    $this->app = $app;
    $this->encrypt = $encrypt;
    $this->forms = $forms;
    $this->admin_id = $id;
}

/**
 * Creates a new administrator using the values POSTed. 
 *
 * @return int The ID# of the newly created administrator.
 */
public function create()
{ 

    // Debug
    debug::add(3, fmsg("Starting to create new administrator and validate form fields"), __FILE__, __LINE__, 'info');

    // Validate form
    $this->forms->validate_form('core:admin');

    // Check validation errors
    if (template::has_errors() === true) { return false; }

    // Insert to DB
    db::insert('admin', array(
        'require_2fa' => app::_post('require_2fa'),
        'require_2fa_phone' => app::_post('require_2fa_phone'),
        'username' => strtolower(app::_post('username')),
        'password' => base64_encode(password_hash(app::_post('password'), PASSWORD_BCRYPT, array('COST' => 25))),
        'full_name' => app::_post('full_name'),
        'email' => app::_post('email'),
        'phone_country' => app::_post('phone_country'),
        'phone' => app::_post('phone'))
    );
    $admin_id = db::insert_id();

    // Add security questions
    for ($x=1; $x <= app::_config('core:num_security_questions'); $x++) { 
        if (!app::has_post('question' . $x)) { continue; }
        if (!app::has_post('answer' . $x)) { continue; }

        // Add to DB
        db::insert('auth_security_questions', array(
            'type' => 'admin',
            'userid' => $admin_id,
            'question' => app::_post('question' . $x),
            'answer' => base64_encode(password_hash(app::_post('answer' . $x), PASSWORD_BCRYPT, array('COST' => 25))))
        );
    }

    // Generate RSA keypair
    $this->encrypt->generate_rsa_keypair((int) $admin_id, 'admin', app::_post('password'));

    /// Debug
    debug::add(1, fmsg("Successfully created new administrator account, {1}", app::_post('username')), __FILE__, __LINE__, 'info');

    // Return
    return $admin_id;

}

/**
 * Loads the administrator profile 
 *
 * @return array An array containing the administrator's profile
 */
public function load()
{ 

    // Get row
    if (!$row = db::get_idrow('admin', $this->admin_id)) { 
        throw new MiscException('no_admin', $this->admin_id);
    }

    // Debug
    debug::add(3, fmsg("Loaded the administrator, ID# {1}", $this->admin_id), __FILE__, __LINE__);

    // Return
    return $row;

}

/**
 * Updates the administrator profile using POST values 
 */
public function update()
{ 

    // Demo check
    if (check_package('demo') && $this->admin_id == 1) { 
        template::add_callout("Unable to modify this account, as it is required for the online demo", 'error');
        return false;
    }

    // Debug
    debug::add(3, fmsg("Starting to update the administrator profile, ID# {1}", $this->admin_id), __FILE__, __LINE__);

    // Set updates array
    $updates = array();
    foreach (array('status','require_2fa','require_2fa_phone', 'full_name','email', 'phone_country', 'phone', 'language', 'timezone') as $var) { 
        if (app::has_post($var)) { $updates[$var] = app::_post($var); }
    }

    // Check password
    if (app::_post('password') != '' && app::_post('password') == app::_post('confirm-password')) { 
        $updates['password'] = base64_encode(password_hash(app::_post('password'), PASSWORD_BCRYPT, array('COST' => 25)));
    }

    // Update database
    db::update('admin', $updates, "id = %i", $this->admin_id);

    // Debug
    debug::add(2, fmsg("Successfully updated administrator profile, ID# {1}", $this->admin_id), __FILE__, __LINE__);

    // Return
    return true;

}

/**
 * Update administrator status 
 *
 * @param string $status The new status of the administrator
 */
public function update_status(string $status, string $note = '')
{ 

    // Demo check
    if (check_package('demo') && $this->admin_id == 1) { 
        template::add_callout("Unable to modify this account, as it is required for the online demo", 'error');
        return false;
    }

    // Update database
    db::update('admin', array('status' => $status), "id = %i", $this->admin_id);

    // Debug
    debug::add(1, fmsg("Updated administrator status, ID: {1}, status: {2}", $this->admin_id, $status), __FILE__, __LINE__);

}

/**
 * Updates the secondary auth hash of the administrator 
 *
 * @param string $sec_hash The secondary auth hash to update on the admin's profile.
 */
public function update_sec_auth_hash(string $sec_hash)
{ 

    // Update database
    db::update('admin', array(
        'sec_hash' => $sec_hash),
    "id = %i", $this->admin_id);

    // Debug
    debug::add(2, fmsg("Updated the secondary auth hash of administrator, ID: {1}", $this->admin_id), __FILE__, __LINE__);

    // Return
    return true;

}

/**
 * Deletes the administrator from the database 
 */
public function delete()
{ 

    // Demo check
    if (check_package('demo') && $this->admin_id == 1) { 
        template::add_callout("Unable to modify this account, as it is required for the online demo", 'error');
        return false;
    }

    // Delete admin from DB
    db::query("DELETE FROM admin WHERE id = %i", $this->admin_id);

    // Debug
    debug::add(1, fmsg("Deleted administrator from database, ID: {1}", $this->admin_id), __FILE__, __LINE__, 'info');

}

/**
 * Creates select options for all administrators in the database 
 *
 * @param int $selected The ID# of the administrator that should be selected.  Defaults to 0.
 * @param bool $add_prefix Whether or not to previs label of each option with "Administrator: "
 *
 * @return string The HTML options that can be included in a <select> list.
 */
public function create_select_options(int $selected = 0, bool $add_prefix = false):string
{ 

    // Debug
    debug::add(5, fmsg("Creating administrator select options"), __FILE__, __LINE__);

    // Create admin options
    $options = '';
    $result = db::query("SELECT id,username,full_name FROM admin ORDER BY full_name");
    while ($row = db::fetch_assoc($result)) { 
        $chk = $row['id'] == $selected ? 'selected="selected"' : '';
        $id = $add_prefix === true ? 'admin:' . $row['id'] : '';

        $name = $add_prefix === true ? 'Administrator: ' : '';
        $name .= $row['full_name'] . '(' . $row['username'] . ')';
        $options .= "<option value=\"$id\" $chk>$name</option>";
    }

    // Return
    return $options;

}


}

