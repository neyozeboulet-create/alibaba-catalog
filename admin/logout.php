<?php
/**
 * Déconnexion administrateur
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';

adminLogout();

setFlash('success', 'Vous avez été déconnecté avec succès.');

redirect('/admin/login.php');