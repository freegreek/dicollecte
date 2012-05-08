<?php

/*
    DATABASE TABLES PATTERNS
    FOR POSTGRESQL

    Lines pattern:
    $dbTables[TABLENAME][COLUMNNAME] = array(data_type,                 -- integer, character varying, etc.
                                             character_maximum_length,  -- must be 0 if cannot be defined
                                             is_nullable,               -- FALSE = NOT NULL
                                             column_default,            -- Value by default. NULL or a string (do not forget double quotes if necessary) 
                                             creation_command           -- NULL or specify optional command at table creation
                                             conversion_spec            -- NULL or specify clause USING in ALTER TABLE xxxxx ALTER COLUMN xxxxxx TYPE xxxxx [USING conversion_spec]
                                             create_index? );           -- TRUE to create an index of this column, FALSE otherwise
                                             
    Note:  data_type in capital letters for serial, primary keys or others datatypes which will not change 
    
*/

// To update the db, update this number (i.e. from 1.0001 to 1.0002)
// then go to http://[homepage]/installer.php
define('DB_VERSION', 1.0002);


/*
    COMMON TABLES FOR ALL PROJECT
    
    Tables suffixes must begin by a letter
*/
$dbTables = array();

$dbTables['users']['id_user']       = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbTables['users']['login']         = array('character varying', DB_LOGINLEN, FALSE, NULL, "CHECK (login <> '')", NULL, FALSE);                 // user name
$dbTables['users']['pw']            = array('character varying', 32, FALSE, NULL, NULL, NULL, FALSE);                                           // password (MD5 hash)
$dbTables['users']['name']          = array('character varying', DB_NAMELEN, TRUE, "'[?]'", NULL, NULL, FALSE);                                 // real name
$dbTables['users']['email']         = array('character varying', DB_EMAILLEN, TRUE, "''", NULL, NULL, FALSE);                                   // e-mail
$dbTables['users']['datetime']      = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, 'extract(epoch FROM datetime)', FALSE);     // accompt creation date
$dbTables['users']['emailnotif']    = array('boolean', 0, FALSE, 'TRUE', NULL, NULL, FALSE);                                                    // notification by e-mail?
$dbTables['users']['enotifauto']    = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);                                                   // notification by e-mail if edit/comment a suggestion?

$dbTables['projects']['id']         = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbTables['projects']['prj']        = array('character varying', 5, FALSE, NULL, "UNIQUE CHECK (prj <> '')", NULL, FALSE);
$dbTables['projects']['label']      = array('character varying', 50, FALSE, NULL, "CHECK (label <> '')", NULL, FALSE);
$dbTables['projects']['version']    = array('real', 0, FALSE, NULL, "CHECK (version <> 0)", NULL, FALSE);
$dbTables['projects']['hidden']     = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);
$dbTables['projects']['closed']     = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);
$dbTables['projects']['nbmembers']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbdictent']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbentgramtag']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbentsemtag']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbprop']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbnotes']    = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbthesent']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbsynsets']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['nbsyns']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);
$dbTables['projects']['lastupdate'] = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, FALSE);


/*
    TABLES FOR EACH PROJECT
    
    tables suffixes must begin by “_”
    %1$s will be replaced by the project identifier
*/
$dbPrjTables = array();

// members
$dbPrjTables['_members']['id_member'] = array('INTEGER PRIMARY KEY REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_members']['rank']     = array('character', 1, FALSE, "'U'", NULL, NULL, FALSE);                                                 // rank: A for admin, C for controller, U for user, B for banned - DEPRECATED
$dbPrjTables['_members']['rk']       = array('smallint', 0, FALSE, '7', NULL, NULL, FALSE);                                                    // level rank: 0 for superadmin, 1 for project sa, 2 for admin, 3 for editor, 5 for controller, 7 for user, 8 for guest, 9 for banned
$dbPrjTables['_members']['nbprop']   = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // number of suggestions
$dbPrjTables['_members']['nbpropok'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // number of integrated suggestions
$dbPrjTables['_members']['nbpropev'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // number of suggestions under consideration
$dbPrjTables['_members']['nbpropval'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                     // number of validated suggestions under consideration
$dbPrjTables['_members']['nbadddict'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                     // number of entries added in the dictionary
$dbPrjTables['_members']['nbactdict'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                     // number of editions in the dictionary
$dbPrjTables['_members']['nbdiceval'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                     // number of evaluations of entries
$dbPrjTables['_members']['nbactthes'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                     // number of actions on the thesaurus
$dbPrjTables['_members']['nbcomments'] = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                    // number of comments
$dbPrjTables['_members']['nbnotes']  = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // number of notes
$dbPrjTables['_members']['nbmsg']    = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // number of messages (forum)
$dbPrjTables['_members']['subscrdt'] = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, FALSE);                             // member since

// dictionary
$dbPrjTables['_dic']['id_entry']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_dic']['id_prop']      = array('integer', 0, TRUE, NULL, NULL, NULL, FALSE);                                                     // REFERENCE TO ID_PROP (NOT STRICT)
$dbPrjTables['_dic']['lemma']        = array('character varying', DB_LEMMALEN, FALSE, NULL, "CHECK (lemma <> '')", NULL, TRUE);                // lemma
$dbPrjTables['_dic']['flags']        = array('character varying', DB_FLAGSLEN, FALSE, "''", NULL, NULL, FALSE);                                // flags
$dbPrjTables['_dic']['dic']          = array('character', 1, FALSE, "'*'", NULL, NULL, FALSE);                                                 // dictionary assignation: (*: common, one-character-tag: custom dictionaries, see [/html/[project]/project_vars.php])
$dbPrjTables['_dic']['lex']          = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // lexical field
$dbPrjTables['_dic']['sem']          = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // semantic field
$dbPrjTables['_dic']['ety']          = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // etymology field
$dbPrjTables['_dic']['nbnotes']      = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // number of notes
$dbPrjTables['_dic']['closed']       = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);                                                 // open to user direct edition?
$dbPrjTables['_dic']['po']           = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Part of speech category
$dbPrjTables['_dic']['is']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Inflectional suffix(es).
$dbPrjTables['_dic']['ds']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Derivational suffix(es).
$dbPrjTables['_dic']['ts']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Terminal suffix(es).
$dbPrjTables['_dic']['ip']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Inflectional prefix(es).
$dbPrjTables['_dic']['dp']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Derivational prefix(es).
$dbPrjTables['_dic']['tp']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Terminal prefix(es).
$dbPrjTables['_dic']['sp']           = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Surface prefix(es).
$dbPrjTables['_dic']['pa']           = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Parts of the compound words.
$dbPrjTables['_dic']['st']           = array('character varying', DB_LEMMALEN, FALSE, "''", NULL, NULL, FALSE);                                // Hunspell: Stem.
$dbPrjTables['_dic']['al']           = array('character varying', DB_LEMMALEN*5, FALSE, "''", NULL, NULL, FALSE);                              // Hunspell: Allomorph(s).
$dbPrjTables['_dic']['ph']           = array('character varying', DB_LEMMALEN, FALSE, "''", NULL, NULL, FALSE);                                // Hunspell: Phonetic. Alternative transliteration for better suggestion.
$dbPrjTables['_dic']['datetime']     = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, TRUE);                              // when the entry was created
$dbPrjTables['_dic']['chk']          = array('character', 1, FALSE, "'2'", NULL, NULL, TRUE);                                                  // check tag (0: alert;  1: to check;  2: unchecked;  3: checked)
$dbPrjTables['_dic']['ifq']          = array('character', 1, FALSE, "''", NULL, NULL, TRUE);                                                   // frequency index: 0-9
$dbPrjTables['_dic']['id_user']      = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, TRUE);                      // id user who add the entry
$dbPrjTables['_dic']['id_edit']      = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);                     // last user who edit the entry

// inflexions
/*
$dbPrjTables['_flex']['id_entry']    = array('INTEGER REFERENCES dicl_%1$s_dic (id_entry)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_flex']['lemma']       = array('character varying', DB_LEMMALEN, FALSE, NULL, "CHECK (lemma <> '')", NULL, TRUE);                // lemma
$dbPrjTables['_flex']['flexion']     = array('character varying', DB_LEMMALEN, FALSE, "''", NULL, NULL, FALSE);                                // flags
$dbPrjTables['_flex']['morph']       = array('character varying', DB_HGRAMMLEN*2, FALSE, "''", NULL, NULL, FALSE);                              // morphology
*/

// flags and rules for word declensions / flexions
$dbPrjTables['_flags']['id_aff']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_flags']['afftype']    = array('character', 1, FALSE, NULL, NULL, NULL, FALSE);                                                  // P for prefixes, S for suffixes
$dbPrjTables['_flags']['mix']        = array('boolean', 0, FALSE, NULL, NULL, NULL, FALSE);                                                    // combination between prefixes and suffixes ?
$dbPrjTables['_flags']['flag']       = array('character varying', 5, FALSE, NULL, NULL, NULL, TRUE);                                           // name of the flag
$dbPrjTables['_flags']['cut']        = array('character varying', DB_AFFCUTLEN, FALSE, NULL, NULL, NULL, FALSE);                               // cut from the lemma
$dbPrjTables['_flags']['add']        = array('character varying', DB_AFFADDLEN, FALSE, NULL, NULL, NULL, FALSE);                               // added to the lemma
$dbPrjTables['_flags']['flags']      = array('character varying', DB_AFFFLAGSLEN, FALSE, "''", NULL, NULL, FALSE);                             // flags for twofold flexions
$dbPrjTables['_flags']['cond']       = array('character varying', DB_AFFCONDLEN, FALSE, "''", NULL, NULL, FALSE);                              // conditional field
$dbPrjTables['_flags']['dic']        = array('character', 1, FALSE, "'*'", NULL, NULL, FALSE);                                                 // dictionary assignation (same as in *prj*_dic)
$dbPrjTables['_flags']['po']         = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Part of speech category
$dbPrjTables['_flags']['is']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Inflectional suffix(es).
$dbPrjTables['_flags']['ds']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Derivational suffix(es).
$dbPrjTables['_flags']['ts']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Terminal suffix(es).
$dbPrjTables['_flags']['dp']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Derivational prefix(es).
$dbPrjTables['_flags']['ip']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Inflectional prefix(es).
$dbPrjTables['_flags']['tp']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Terminal prefix(es).
$dbPrjTables['_flags']['sp']         = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Surface prefix(es).
$dbPrjTables['_flags']['pa']         = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Parts of the compound words.
$dbPrjTables['_flags']['comment']    = array('character varying', DB_AFFCOMMENTLEN, FALSE, "''", NULL, NULL, FALSE);                           // Hunspell: comment.

// where are stored suggestions
$dbPrjTables['_prop']['id_prop']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_prop']['id_entry']    = array('INTEGER REFERENCES dicl_%1$s_dic (id_entry)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_prop']['id_user']     = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_prop']['lemma']       = array('character varying', DB_LEMMALEN, FALSE, NULL, "CHECK (lemma <> '')", NULL, FALSE);               // lemma
$dbPrjTables['_prop']['flags']       = array('character varying', DB_FLAGSLEN, FALSE, "''", NULL, NULL, FALSE);                                // flags
$dbPrjTables['_prop']['dic']         = array('character', 1, FALSE, "'*'", NULL, NULL, FALSE);                                                 // dictionary assignation (same as in *prj*_dic)
$dbPrjTables['_prop']['lex']         = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // lexical field
$dbPrjTables['_prop']['sem']         = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // semantic field
$dbPrjTables['_prop']['ety']         = array('character varying', DB_LEXLEN, FALSE, "''", NULL, NULL, FALSE);                                  // etymology field
$dbPrjTables['_prop']['date']        = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, 'extract(epoch FROM date)', FALSE);       // when
$dbPrjTables['_prop']['prio']        = array('integer', 0, FALSE, '1', NULL, NULL, FALSE);                                                     // priority: 0, 1, 2, 3
$dbPrjTables['_prop']['nbcomments']  = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // number of comments
$dbPrjTables['_prop']['action']      = array('character', 1, FALSE, NULL, NULL, NULL, FALSE);                                                  // suggest to (+ : add, - : delete, > : modify)
$dbPrjTables['_prop']['value']       = array('character', 1, FALSE, "'?'", NULL, NULL, FALSE);                                                 // (V: validated, R: rejected, S: suspended, !: dubious, I:integrated) 
$dbPrjTables['_prop']['tab']         = array('character', 1, FALSE, "'E'", NULL, NULL, TRUE);                                                  // table (E : evaluation, R : rejected, T : trash)
$dbPrjTables['_prop']['ph']          = array('character varying', DB_LEMMALEN, FALSE, "''", NULL, NULL, FALSE);                                // Hunspell: Phonetic. Alternative transliteration for better suggestion.
$dbPrjTables['_prop']['st']          = array('character varying', DB_LEMMALEN, FALSE, "''", NULL, NULL, FALSE);                                // Hunspell: Stem.
$dbPrjTables['_prop']['al']          = array('character varying', DB_LEMMALEN*5, FALSE, "''", NULL, NULL, FALSE);                              // Hunspell: Allomorph(s).
$dbPrjTables['_prop']['po']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Part of speech category
$dbPrjTables['_prop']['is']          = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Inflectional suffix(es).
$dbPrjTables['_prop']['ds']          = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Derivational suffix(es).
$dbPrjTables['_prop']['ts']          = array('character varying', DB_HGRAMMLEN*4, FALSE, "''", NULL, NULL, FALSE);                             // Hunspell: Terminal suffix(es).
$dbPrjTables['_prop']['pa']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Parts of the compound words.
$dbPrjTables['_prop']['sp']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Surface prefix.
$dbPrjTables['_prop']['ip']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Inflectional prefix.
$dbPrjTables['_prop']['dp']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Derivational prefix.
$dbPrjTables['_prop']['tp']          = array('character varying', DB_HGRAMMLEN, FALSE, "''", NULL, NULL, FALSE);                               // Hunspell: Terminal prefix.
$dbPrjTables['_prop']['nbnotif']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // number of people who subscribed

// comments
$dbPrjTables['_comments']['id_com']  = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_comments']['id_prop'] = array('INTEGER REFERENCES dicl_%1$s_prop (id_prop)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_comments']['id_user'] = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_comments']['login']   = array('character varying', DB_LOGINLEN, FALSE, NULL, NULL, NULL, FALSE);                                // user name
$dbPrjTables['_comments']['prop_user'] = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, TRUE);                    // who created the proposition
$dbPrjTables['_comments']['comment'] = array('character varying', DB_COMMENTLEN + (int) (DB_COMMENTLEN/2), FALSE, NULL, NULL, NULL, FALSE);    // content
$dbPrjTables['_comments']['datetime'] = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, 'extract(epoch FROM datetime)', TRUE);   // when the comment was posted
$dbPrjTables['_comments']['autocom'] = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);                                                 // autocomment ?

// subscribing to proposition
$dbPrjTables['_propsub']['id_prop']  = array('INTEGER REFERENCES dicl_%1$s_prop (id_prop)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_propsub']['id_user']  = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);

// notes
$dbPrjTables['_notes']['id_note']    = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_notes']['id_entry']   = array('INTEGER REFERENCES dicl_%1$s_dic (id_entry)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_notes']['id_user']    = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_notes']['login']      = array('character varying', DB_LOGINLEN, FALSE, NULL, NULL, NULL, FALSE);                                // user name
$dbPrjTables['_notes']['lemma']      = array('character varying', DB_LEMMALEN, FALSE, NULL, "CHECK (lemma <> '')", NULL, NULL, FALSE);         // lemma
$dbPrjTables['_notes']['note']       = array('character varying', DB_NOTELEN  + (int) (DB_NOTELEN/2), FALSE, NULL, "CHECK (note <> '')", NULL, FALSE);  // content
$dbPrjTables['_notes']['datetime']   = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, 'extract(epoch FROM datetime)', FALSE);   // when the note was posted

/*
// the thesaurus
*/
// DEPRECATED
$dbPrjTables['_thes']['id_word']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_thes']['word']        = array('character varying', DB_THESENTRYLEN, FALSE, NULL, "UNIQUE CHECK (word <> '')", NULL, TRUE);      // word
$dbPrjTables['_thes']['nbclass']     = array('smallint', 0, FALSE, NULL, "CHECK (nbclass > 0)", NULL, FALSE);                                  // number of meanings
$dbPrjTables['_thes']['syn']         = array('character varying', DB_SYNSLEN, FALSE, NULL, NULL, NULL, FALSE);                                 // meanings and synonyms, each class is separated by #
$dbPrjTables['_thes']['lastedit']    = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // UNIX timestamp, used to lock the entry
$dbPrjTables['_thes']['id_user']     = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // id_user of last edtion
$dbPrjTables['_thes']['lock']        = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // UNIX timestamp lock
$dbPrjTables['_thes']['keyid']       = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                      // key to open the lock (id_user)

// the thesaurus history
// DEPRECATED
$dbPrjTables['_thist']['id_hist']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_thist']['word']        = array('character varying', DB_THESENTRYLEN, FALSE, NULL, NULL, NULL, TRUE);                             // word
$dbPrjTables['_thist']['nbclass']     = array('smallint', 0, FALSE, NULL, "CHECK (nbclass > 0)", NULL, FALSE);                                  // number of meanings
$dbPrjTables['_thist']['syn']         = array('character varying', DB_SYNSLEN, FALSE, NULL, NULL, NULL, FALSE);                                 // meanings and synonyms, each class is separated by #
$dbPrjTables['_thist']['lastedit']    = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // UNIX timestamp, used to lock the entry
$dbPrjTables['_thist']['id_user']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // id_user of last edtion

/*
// the synsets
*/
$dbPrjTables['_syns']['id_synset']    = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_syns']['pos']          = array('character varying', DB_POSSYNLEN, FALSE, NULL, "CHECK (pos <> '')", NULL, FALSE);                 // part of speech
$dbPrjTables['_syns']['tags']         = array('character varying', DB_LEXLEN, FALSE, NULL, NULL, NULL, FALSE);                                   // tags
$dbPrjTables['_syns']['synset']       = array('character varying', DB_SYNSLEN, FALSE, NULL, NULL, NULL, FALSE);                                  // synonyms
$dbPrjTables['_syns']['nbsyn']        = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                      // number of synonyms
$dbPrjTables['_syns']['lastedit']     = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, FALSE);                              // UNIX timestamp, used to lock the entry
$dbPrjTables['_syns']['id_user']      = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                       // id_user of last edtion
$dbPrjTables['_syns']['deleted']      = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, TRUE);                                                   // deleted ?
$dbPrjTables['_syns']['nbhist']       = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                      // number of archives for this synset

// the synsets history
$dbPrjTables['_shist']['id_hist']     = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_shist']['id_synset']   = array('INTEGER REFERENCES dicl_%1$s_syns (id_synset)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_shist']['pos']         = array('character varying', DB_POSSYNLEN, FALSE, NULL, "CHECK (pos <> '')", NULL, FALSE);                 // part of speech
$dbPrjTables['_shist']['tags']        = array('character varying', DB_LEXLEN, FALSE, NULL, NULL, NULL, FALSE);                                   // tags
$dbPrjTables['_shist']['synset']      = array('character varying', DB_SYNSLEN, FALSE, NULL, NULL, NULL, FALSE);                                  // synonyms
$dbPrjTables['_shist']['nbsyn']       = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                      // number of synonyms
$dbPrjTables['_shist']['lastedit']    = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                      // UNIX timestamp
$dbPrjTables['_shist']['id_user']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                      // id_user of last edtion

/*
// log
*/
$dbPrjTables['_log']['id_log']        = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_log']['id_user']       = array('INTEGER REFERENCES dicl_users (id_user)', 0, FALSE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_log']['id']            = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                     // where the action occured
$dbPrjTables['_log']['cat']           = array('character', 1, FALSE, "'?'", NULL, NULL, FALSE);                                                 // category (A: announce, D: dictionary, E: evaluation, T: thesaurus, N: note, S: system)
$dbPrjTables['_log']['action']        = array('character', 1, FALSE, "'?'", NULL, NULL, FALSE);                                                 // category (+: add, -: delete, >: change , A: admin)
$dbPrjTables['_log']['label']         = array('character varying', DB_LOGLABELLEN + (int) (DB_LOGLABELLEN/2), FALSE, NULL, NULL, NULL, FALSE);  // label of the registered action
$dbPrjTables['_log']['datetime']      = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, TRUE);                              // when the action occured

/*
// forum
*/
$dbPrjTables['_forum']['id_forum']      = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_forum']['label']         = array('character varying', DB_FORUMLBLLEN, FALSE, NULL, "CHECK (label <> '')", NULL, NULL, FALSE);        // label
$dbPrjTables['_forum']['descr']         = array('character varying', DB_FORUMDESCRLEN, FALSE, "''", NULL, NULL, NULL, FALSE);                       // description
$dbPrjTables['_forum']['numorder']      = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // sorted by
$dbPrjTables['_forum']['nbmsg']         = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // number of messages
$dbPrjTables['_forum']['nbthreads']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // number of threads
$dbPrjTables['_forum']['updatedt']      = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // when the thread was updated
$dbPrjTables['_forum']['id_user_up']    = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);                       // who did the last update
$dbPrjTables['_forum']['id_thread']     = array('integer', 0, TRUE, '0', NULL, NULL, FALSE);                                                        // where was the last update
$dbPrjTables['_forum']['msgnum']        = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // message id

$dbPrjTables['_thread']['id_thread']    = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_thread']['id_forum']     = array('INTEGER REFERENCES dicl_%1$s_forum (id_forum)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_thread']['id_user']      = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_thread']['label']        = array('character varying', DB_FORUMLBLLEN, FALSE, NULL, "CHECK (label <> '')", NULL, NULL, FALSE);        // label
$dbPrjTables['_thread']['nbmsg']        = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // number of messages
$dbPrjTables['_thread']['msgcount']     = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // number of messages posted
$dbPrjTables['_thread']['creationdt']   = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, FALSE);                               // when the thread was created
$dbPrjTables['_thread']['updatedt']     = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, TRUE);                                // when the thread was updated
$dbPrjTables['_thread']['id_user_up']   = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);                       // who did the last update
$dbPrjTables['_thread']['msgnum']       = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // message id
$dbPrjTables['_thread']['flow']         = array('character', 1, FALSE, "'C'", NULL, NULL, TRUE);                                                    // flow place
$dbPrjTables['_thread']['tag']          = array('character', 1, FALSE, "'?'", NULL, NULL, FALSE);                                                   // tag
$dbPrjTables['_thread']['locked']       = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);                                                   // closed ?
$dbPrjTables['_thread']['solved']       = array('boolean', 0, FALSE, 'FALSE', NULL, NULL, FALSE);                                                   // solved ?
$dbPrjTables['_thread']['nbnotif']      = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // number of people who subscribed

$dbPrjTables['_msg']['id_msg']          = array('SERIAL PRIMARY KEY', 0, TRUE, NULL, NULL, NULL, FALSE);
$dbPrjTables['_msg']['id_thread']       = array('INTEGER REFERENCES dicl_%1$s_thread (id_thread)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_msg']['id_user']         = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_msg']['msg']             = array('character varying', DB_FORUMMSGLEN + (int) (DB_FORUMMSGLEN/2), FALSE, NULL, NULL, NULL, FALSE);    // content
$dbPrjTables['_msg']['msgnum']          = array('integer', 0, FALSE, '0', NULL, NULL, FALSE);                                                       // message order
$dbPrjTables['_msg']['creationdt']      = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, TRUE);                                // when the message was posted
$dbPrjTables['_msg']['updatedt']        = array('integer', 0, FALSE, 'extract(epoch FROM now())', NULL, NULL, FALSE);                               // when the message was updated

// subscribing to thread
$dbPrjTables['_threadsub']['id_thread'] = array('INTEGER REFERENCES dicl_%1$s_thread (id_thread)', 0, TRUE, NULL, NULL, NULL, TRUE);
$dbPrjTables['_threadsub']['id_user']   = array('INTEGER REFERENCES dicl_users (id_user)', 0, TRUE, NULL, NULL, NULL, FALSE);

?>
