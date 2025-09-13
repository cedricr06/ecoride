<?php
// app/Controllers/profil_avatar.ctrl.php

global $db; // Rend la connexion BDD globale disponible dans ce script.

require_login();
require_post();
verify_csrf();

// Vérifs de base
if (!isset($_FILES['avatar']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
  flash('error','Aucun fichier n\'a été envoyé.'); redirect('profil'); exit;
}

$error = $_FILES['avatar']['error'];
if ($error !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille autorisée par le serveur.',
        UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille autorisée par le formulaire.',
        UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement envoyé.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été envoyé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Écriture du fichier impossible sur le serveur.',
        UPLOAD_ERR_EXTENSION  => 'Une extension PHP a arrêté l\'envoi du fichier.',
    ];
    flash('error', $messages[$error] ?? 'Une erreur inconnue est survenue lors de l\'envoi.');
    redirect('profil'); exit;
}

$max = 2 * 1024 * 1024; // 2 Mo
if ($_FILES['avatar']['size'] > $max) {
  flash('error','Image trop lourde.'); redirect('profil'); exit;
}

// Vérif MIME réelle
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['avatar']['tmp_name']);
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($allowed[$mime])) {
  flash('error','Format non autorisé (jpg, png, webp).'); redirect('profil'); exit;
}

try {
    // --- Traitement du fichier ---
    $uid = (int)$_SESSION['user']['id'];
    $source_path = $_FILES['avatar']['tmp_name'];

    // --- Redimensionnement et Crop ---
    // 2. On ouvre l'image depuis son emplacement temporaire original.
    [$w, $h] = getimagesize($source_path);
    $src_image = match ($mime) { // On utilise le MIME type original pour ouvrir le fichier
        'image/jpeg' => imagecreatefromjpeg($source_path),
        'image/png'  => imagecreatefrompng($source_path),
        'image/webp' => imagecreatefromwebp($source_path),
    };

    // Sécurité : si GD n'arrive pas à ouvrir l'image, on arrête.
    if ($src_image === false) {
        throw new Exception('Impossible de lire l\'image. Le fichier est peut-être corrompu.');
    }

    $size = min($w, $h);
    $crop = imagecrop($src_image, ['x' => (int)(($w - $size) / 2), 'y' => (int)(($h - $size) / 2), 'width' => $size, 'height' => $size]);

    $dst_image = imagecreatetruecolor(256, 256);
    imagecopyresampled($dst_image, $crop ?: $src_image, 0, 0, 0, 0, 256, 256, imagesx($crop ?: $src_image), imagesy($crop ?: $src_image));

    // 3. On génère le nom final et on sauvegarde l'image traitée en JPEG.
    $dir = __DIR__ . '/../../public/uploads/avatars/' . $uid;
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier de destination. Vérifiez les droits.');
        }
    }
    $filename = bin2hex(random_bytes(12)) . '.jpg'; // On force l'extension en .jpg pour la cohérence
    $targetPath = $dir . '/' . $filename; // Le chemin complet sur le disque

    if (!imagejpeg($dst_image, $targetPath, 88)) {
        throw new Exception('Impossible d\'enregistrer l\'image finale.');
    }
    imagedestroy($src_image); if ($crop) imagedestroy($crop); imagedestroy($dst_image);

} catch (Exception $e) {
    // Log de l'erreur pour le développeur
    error_log('Avatar Upload Failed: ' . $e->getMessage());
    // Message pour l'utilisateur
    flash('error', 'Une erreur technique est survenue lors du traitement de l\'image.');
    redirect('profil');
    exit;
}

// 4. Préparer le chemin web pour la BDD (doit correspondre au $filename généré)
$webPath = '/uploads/avatars/' . $uid . '/' . $filename;

// 5. Supprimer l'ancien avatar (fichier et BDD)
$old = $db->prepare('SELECT avatar_path FROM utilisateurs WHERE id = :id');
$old->execute([':id'=>$uid]);
$oldPath = $old->fetchColumn();
if ($oldPath && $oldPath !== $webPath) {
  $abs = __DIR__ . '/../../public' . $oldPath;
  if (is_file($abs)) @unlink($abs);
}

// 6. Mettre à jour la base de données
$upd = $db->prepare('UPDATE utilisateurs SET avatar_path = :p WHERE id = :id');
$upd->execute([':p'=>$webPath, ':id'=>$uid]);

flash('success','Photo mise à jour.');
redirect('profil'); exit;
