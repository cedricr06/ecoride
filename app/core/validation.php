<?php

/**
 * Validation serveur simple.
 * Retourne array($clean, $errors).
 */

if (!function_exists('validate')) {
    function validate($data, $rules)
    {
        $errors = array();
        $clean  = array();

        // helper longueur compatible mbstring
        $strlen_mb = function ($s) {
            if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
            return strlen($s);
        };

        foreach ($rules as $field => $ruleString) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $rulesArr = explode('|', $ruleString);

            // Nettoyage
            if (is_string($value)) {
                $value = trim($value);
                // si la règle contient "string", normalise CRLF
                if (strpos($ruleString, 'string') !== false) {
                    $value = str_replace("\r\n", "\n", $value);
                }
            }
            $clean[$field] = $value;

            foreach ($rulesArr as $rule) {
                $params = array();
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramStr) = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                // required
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field] = 'Ce champ est requis.';
                    break;
                }

                // champs vides (non required) → on saute le reste
                if ($value === null || $value === '') continue;

                switch ($rule) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Adresse email invalide.';
                        }
                        break;

                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field] = 'Valeur invalide.';
                            break 2;
                        }
                        $min = isset($params[0]) && $params[0] !== '' ? (int)$params[0] : null;
                        $max = isset($params[1]) && $params[1] !== '' ? (int)$params[1] : null;
                        $len = $strlen_mb($value);
                        if ($min !== null && $len < $min) {
                            $errors[$field] = "Minimum {$min} caractères.";
                        } elseif ($max !== null && $len > $max) {
                            $errors[$field] = "Maximum {$max} caractères.";
                        }

                        break;

                    case 'password':
                        $min = isset($params[0]) && $params[0] !== '' ? (int)$params[0] : 8;
                        $max = isset($params[1]) && $params[1] !== '' ? (int)$params[1] : 72;
                        $len = $strlen_mb((string)$value);
                        if ($len < $min) {
                            $errors[$field] = "Le mot de passe doit faire au moins {$min} caractères.";
                        } elseif ($len > $max) {
                            $errors[$field] = "Le mot de passe ne doit pas dépasser {$max} caractères.";
                        }
                        break;
                        $strong = isset($params[2]) && strtolower($params[2]) === 'strong';
                        if ($strong) {
                            if (
                                preg_match('/\s/', $value) ||              // pas d'espace
                                !preg_match('/[a-z]/', $value) ||          // minuscule
                                !preg_match('/[A-Z]/', $value) ||          // majuscule
                                !preg_match('/\d/', $value)    ||          // chiffre
                                !preg_match('/[^A-Za-z0-9]/', $value)      // symbole
                            ) {
                                $errors[$field] = "Utilise au moins une minuscule, une majuscule, un chiffre, un symbole et aucun espace.";
                            }
                        }
                        break;


                    case 'checkbox':
                        if (!in_array($value, array('on', '1', 1, true), true)) {
                            $errors[$field] = 'Vous devez cocher cette case.';
                        } else {
                            $clean[$field] = true;
                        }
                        break;

                    case 'in':
                        if (!in_array($value, $params, true)) {
                            $errors[$field] = 'Valeur non autorisée.';
                        }
                        break;
                    case 'speudo':
                        // lettres/chiffres/underscore, 3–10
                        if (!preg_match('/^[A-Za-z0-9_]{3,10}$/', $value)) {
                            $errors[$field] = 'Pseudo invalide (3–10 caractères, lettres/chiffres/_).';
                        }
                        break;

                    case 'nom-prenom':
                        // nom/prénom avec accents, espace, ' et -, 2–40
                        if (!preg_match('/^[\p{L}\p{M}\'\- ]{2,40}$/u', $value)) {
                            $errors[$field] = 'Nom/prénom invalide.';
                        }
                        break;

                    case 'numeros':
                        // format FR simple: +33 ou 0, puis 9 chiffres (groupés possibles)
                        if (!preg_match('/^(?:\+33|0)[1-9](?: ?\d{2}){4}$/', $value)) {
                            $errors[$field] = 'Téléphone invalide.';
                        }
                        break;

                    case 'code_postal':
                        if (!preg_match('/^\d{5}$/', $value)) {
                            $errors[$field] = 'Code postal invalide.';
                        }
                        break;

                }

                if (isset($errors[$field])) break;
            }
        }

        return array($clean, $errors);
    }
}
