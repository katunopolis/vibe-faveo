<?php
// Create admin user script for Faveo
\ = new App\Models\User();
\->user_name = 'admin';
\->first_name = 'System';
\->last_name = 'Admin';
\->email = 'admin@example.com';
\->password = Hash::make('Admin@123');
\->active = 1;
\->role = 'admin';
\->save();
\ = new App\Models\User_role();
\->role_id = 1;
\->user_id = \->id;
\->save();
echo 'Admin user created successfully!';
