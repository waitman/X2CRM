<?php
/*****************************************************************************************
 * X2Engine Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2015 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/

/**
 * A behavior to automatically parse the software for translation calls on text,
 * add that text to our translation files, consolidate duplicate entries into
 * common.php, and then translate all missing entries via Google Translate API.
 * To run the translation automation, navigate to "admin/automateTranslation" in
 * the software. End users should never need to run this code (and in fact without
 * the Google API Key file and Google Translation Billing API configured it
 * will not work). This class is primarily designed for developer use to update
 * translations for new releases.
 * @package application.components
 * @author "Jake Houser" <jake@x2engine.com>, "Demitri Morgan" <demitri@x2engine.com>
 */
class X2TranslationBehavior extends CBehavior {
    
    
    /*
     * This behemoth of a regex is now generated by getRegex with configurable
     * special characters.
     * 
     * const REGEX = '/(?:(?<installer>installer_tr?)\s*|Yii::\s*t\s*)\(\s*(?(installer)|(?:(?<openquote1>")|\')(?<module>\w+)(?(openquote1)"|\')\s*,)\s*(?<message>(?:((?<openquote2>")|\')(?:(?(openquote2)\\\\"|\\\\\')|(?(openquote2)\'|")|\w|\s|[\(\)\{\}_\.\-\,\*\#\|\&\!\?\/\<\>;:])+(?(openquote2)"|\')((\.\n\s*)|(\n\s*\.\s*))?)+)/';
     */

    private $_regex;
    private $_allowedChars;
   
    public $verbose = false;
    
    public $newMessages = 0;
    public $addedToCommon = 0;
    public $messagesRemoved = 0;
    public $untranslated = 0;
    public $characterCount = 0;
    public $customMessageCount = 0;
    public $languageStats = array();
    public $errors = array();
    public $limitReached = false;
    
    /**
     * The regular expression for matching calls to Yii::t
     *
     * See protected/tests/data/messageparser/modules1.php for examples of what
     * will be matched by this pattern.
     * @param string $allowedChars valid special characters to match inside of translation calls
     * @return string the constructed regex pattern
     */
    public function getRegex($allowedChars = "(){}_.-,+^%@*#|&!?/<>;:"){
        if(!isset($this->_regex) || $this->_allowedChars !== $allowedChars){
            // Forward slash for delimeter
            $regex = '/';

            /*
             * Non-capturing match installer_tr or Yii::t
             * installer_tr is the translation function for requirements and installation
             * Yii::t can optionally have spaces on either side of the 't'
             */
            $regex .= '(?:(?<installer>installer_tr?)\s*|Yii::\s*t\s*)';

            /*
             * If installer has been captured, match nothing followed by optional whitepsace and a comma
             * Otherwise, match an opening quote, a word, and a closing quote followed by optional
             * white space and a comma. This block corresponds to the translation file in a Yii::t
             * call i.e. the pattern will now match "Yii::t('app',"
             */
            $regex .= '\(\s*(?(installer)|(?:(?<openquote1>")|\')(?<module>\w+)(?(openquote1)"|\')\s*,)';

            //Match optional whitespace. This separation exists to clearly distinguish the next block
            $regex .= '\s*';

            /*
             * This piece defines the start of the message. Begin the message
             * named subpattern and match the initial quote as either a single or double
             * quote, and based on the openquote2 subpattern we will know which type it
             * was.
             */
            $regex .= '(?<message>(?:((?<openquote2>")|\')';

            // Everything that follows is considered the text of the message

            /*
             * The first thing we have the ability to match in a message is an escaped
             * quote. If we matched a doublequote at first, we can match a \" by adding
             * the \\\\" pattern. Otherwise, we match escaped singlequotes with \\\\\\'
             * which matches \ followed by \'
             */
            $regex .= '(?:(?(openquote2)\\\\"|\\\\\')|';

            /*
             * The next valid match is an unescaped quote. If we matched a double quote
             * first, that equates to \' and if we matched a single quote first, that
             * equates to "
             */
            $regex .= '(?(openquote2)\'|")|';

            /*
             * The next things we're allowed to have in a translation message are
             * word characters and whitespace. Fairly self-explanatory.
             */
            $regex .= '\w|\s|';

            /*
             * We can also match non-word characters that might be found inside of
             * translation calls. Certain special characters are not allowed (like $)
             * for various reasons. The $allowedChars parameter for this function
             * builds this list.
             */
            $chars = str_split($allowedChars);
            $regex .= '[\\'.implode('\\',$chars).']';

            /*
             * Close our current capturing segment and expect to see one or more
             * of the previous pattern (the actual letters of the message)
             */
            $regex .= ')+';

            /*
             * Next, we match the closing quote for the translation message. If we
             * matched a double quote, it'll be a ", otherwise '
             */
            $regex .= '(?(openquote2)"|\')';

            /*
             * Next, we can optionally match either a . followed by a newline and optional 
             * whitespace or a newline followed by optional whitespace and a .
             * This pattern matches multiline translation calls with concatenated strings
             */
            $regex .= '((\h*\.\h*\n\h*)|(\h*\n\h*\.\h*))?';

            /*
             * Finally, expect to see one or more lines of messages and close the
             * message named subpattern
             */
            $regex .= ')+)';

            //Closing delimiter
            $regex .= '/';

            $this->_allowedChars = $allowedChars;
            $this->_regex = $regex;
        }
        return $this->_regex;
    }

    /**
     * Add missing translations to files, first step of automation.
     *
     * Function to find all untralsated text in the software, and then take that
     * array of messages and add them to translation files for all languages.
     * Called in {@link X2TranslationAction::run} function as part of the full
     * translation suite.
     */
    public function addMissingTranslations(){
        $this->verbose && print("Searching for missing translations...\n");
        $messages = $this->getAttributeLabels();
        $files = $this->fileList();
        $this->verbose && print("Searching filesystem for translation calls...\n");
        foreach($files as $file){
            $messages = array_merge_recursive($messages, $this->getMessageList($file));
        }
        $languages = $this->getValidLanguagePacks();
        $this->verbose && print("Adding new messages to language packs...\n");
        foreach ($languages as $lang) {
            if ($lang != '.' && $lang != '..') { // Don't include the current or parent directory.
                foreach ($messages as $fileName => $messageList) {
                    $file = Yii::app()->basePath."/messages/$lang/$fileName.php";
                    $common = Yii::app()->basePath."/messages/$lang/common.php";
                    $this->addMessages($file, $messageList, $common); // Add each message to the end of the relevant file.
                }
            }
        }
        $this->verbose && print("Adding missing translations complete!\n");
    }
    
    public function getAttributeLabels() {
        $this->verbose && print("Checking for untranslated attribute labels...\n");
        $fields = Yii::app()->db->createCommand()
                ->select('attributeLabel, modelName')
                ->from('x2_fields')
                ->where('custom=0')
                ->queryAll(); // Grab all the attribute labels for fields for all non-custom modules that might need to be translated.
        foreach ($fields as $field) {
            if ($translationFile = $this->getTranslationFileName($field['modelName'])) { // Get the name of the translation file each model is associated with.
                $messages[$translationFile][] = $field['attributeLabel']; // Add the attribute labels to our list of text to be translated.
            }
        }
        return $messages;
    }
    
    /**
     * Converts model name to translation file name.
     *
     * Helper method called in {@link getMessageList} to
     * find the correct translation file for a model. This is necessary because some
     * models have class names like Quote or Opportunity but their file names are
     * quotes and opportunities.
     *
     * @param string $modelName The name of the model to look up the related translation file for.
     * @return string|boolean Returns the name of the translation file to use, or false if a correct file cannot be found.
     */
    public function getTranslationFileName($modelName){
        $excludeList = array(
            'BugReports', // Don't translate bug reports... not really used as a module
        );
        $modelToTranslation = array(
            'Accounts' => 'accounts',
            'Actions' => 'actions',
            'Calendar' => 'calendar',
            'AnonContact' => 'marketing',
            'Campaign' => 'marketing',
            'Fingerprint' => 'marketing',
            'Charts' => 'charts',
            'Contacts' => 'contacts',
            'Docs' => 'docs',
            'EmailInboxes' => 'app',
            'Groups' => 'groups',
            'Media' => 'media',
            'Opportunity' => 'opportunities',
            'Product' => 'products',
            'Quote' => 'quotes',
            'Reports' => 'reports',
            'Services' => 'services',
            'Topics' => 'topics',
            'X2Leads' => 'x2Leads',
            'X2List' => 'contacts',
        );
        if(isset($modelToTranslation[$modelName])){
            return $modelToTranslation[$modelName];
        }else{
            if(!in_array($modelName, $excludeList)){
                if(!isset($this->errors['missingAttributes'])){
                    $this->errors['missingAttributes'] = array();
                }
                if(!in_array($modelName, $this->errors['missingAttributes'])){
                    $this->errors['missingAttributes'][] = $modelName;
                }
            }
            return false; // Translation file not found for the specified model.
        }
    }
    
    /**
     * Parse file structure for valid files.
     *
     * Returns a list of all files in the codebase that are eligible for searching
     * for Yii::t calls within.
     *
     * @param string $revision Unused, may implement comparison between Git revisions rather than searching all files.
     * @return array List of files to be parsed for Yii::t calls
     */
    public function fileList($revision = null){
        $this->verbose && print("Generating list of files to search for translation calls...\n");
        $cwd = Yii::app()->basePath;
        $fileList = array();
        $basePath = realpath($cwd.'/../');
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath), RecursiveIteratorIterator::SELF_FIRST); // Build PHP File Iterator to loop through valid directories
        foreach($objects as $name => $object){
            if(!$object->isDir()){ // Make sure it's actually a file if we're going to try to parse it.
                $relPath = str_replace("$basePath/", '', $name); // Get the relative path to it.
                if(!$this->excludePath($relPath)){ // Make sure the file is not in one of the excluded diectories.
                    $fileList[] = $name;
                }
            }
        }
        return $fileList;
    }
    
    /**
     * Returns true or false based on whether a path should be parsed for
     * messages.
     *
     * Some files in the software don't need to be translated. Yii provides all
     * of its own translations for the framework directory, and there are other
     * files which simply have no possibility of having Yii::t calls in them.
     * Ignoring these files speeds up the process, especially since framework is
     * a very large directory.
     *
     * @param string $relPath Paths to folders which should not be included in the Yii::t search
     * @return boolean True if file should be excluded from the search, false if the file is OK.
     */
    public function excludePath($relPath){
        $paths = array(
            'framework', // Yii handles its own translations
            'protected/data', //Data files do not have Yii::t calls
            'protected/messages', // These are the translation files...
            'protected/extensions', // Extensions are rarely translated and generally don't display text.
            'protected/integration', // Integrations are rarely translated and generally don't display text.
            'protected/migrations', // Migrations are all back-end and have no text
            'protected/tests', // Unit tests have no translation calls
            'backup', // Backup of older files that may no longer be relevant
        );
        foreach($paths as $path)
            if(strpos($relPath, $path) === 0) // We found the excluded directory in the relative path.
                return true;
        return !preg_match('/\.php$/', $relPath); // Only look in PHP files.
    }
    
    /**
     * Gets a list of Yii::t calls.
     *
     * Helper function called by {@link addMissingTranslations}
     * to get a list of messages found in Yii::t calls found in the software in
     * an easily parsed array format. Also checks attribute labels of non-custom
     * modules in the x2_fields table.
     *
     * @return array An array of messages found in the software that need to be added to the translation files.
     */
    public function getMessageList($file) {
        $messages = array();
        $newMessages = $this->parseFile($file); // Parse the file for all messages within Yii::t calls.
        foreach ($newMessages as $fileName => $messageList) { // Loop through the found messages.
            if (array_key_exists($fileName, $messages)) { // We've already got this file in our return array
                $messages[$fileName] = array_unique(array_merge($messages[$fileName],
                        array_keys($messageList))); // Merge the new messages with the old messages for the given file
            } else {
                $messages[$fileName] = array_unique(array_keys($messageList)); // Otherwise, define the messages we found as the initial data set for this file.
            }
        }
        return $messages;
    }
    
    /**
     * Return Yii::t calls in a specific file
     *
     * Helper method called in {@link getMessageList}
     * Parses a file and returns an associative array of module names to messages
     * for that file.
     *
     * @param string $path Filepath to the file to be checked by the REGEX
     * @return array An array of messages in Yii::t calls in the provided file.
     */
    public function parseFile($path){
        if(!file_exists($path))
            return array();
        preg_match_all($this->getRegex(), file_get_contents($path), $matches);
        // Modify the match array to incorporate the special installer_t case
        foreach($matches['installer'] as $index => $groupText)
            if($groupText != '')
                $matches['module'][$index] = 'install';
            
        $messages = array_fill_keys(array_unique($matches['module']), array());
        foreach($matches['message'] as $index => $message){
            $message = $this->parseRegexMatch($message);
            $message = str_replace("\\'", "'", $message);
            //$message = str_replace("'", "\\'", $message);
            $messages[$matches['module'][$index]][$message] = '';
        }
        if(isset($messages['yii'])){
            unset($messages['yii']);
        }
        return $messages;
    }
    
    public function parseRegexMatch($message) {
        $ret = preg_replace("/(\'|\")((\h*\.\h*(\r\n?|\n)\h*)|(\h*(\r\n?|\n)\h*\.\h*))(\'|\")/", '', $message);
        if (strpos($ret, '"') === 0 || strpos($ret, "'") === 0) {
            $ret = substr($ret, 1);
        }
        if (strpos(strrev($ret), '"') === 0 || strpos(strrev($ret), "'") === 0) {
            $ret = substr($ret, 0, -1);
        }
        return $ret;
    }
    
    /**
     * Commented out until unit test is built.
     * @param type $file
     * @param type $messageList
     */
    public function addMessages($file, $messageList, $common = null) {
        if (file_exists($file)) {
            $fileMessages = require $file;
            if (isset($common) && file_exists($common)) {
                $messages = array_merge(array_keys(require $file),
                        array_keys(require $common)); // Get all of the messages already in the appropriate language as well as common.php
            } else {
                $messages = array_keys(require $file);
            }
            $diff = array_diff($messageList, $messages); // Create a diff array of messages not already in the provided language file or common.php
            if (!empty($diff)) {
                $contents = file_get_contents($file); // Grab the array of messages from the translation file.
                foreach ($diff as $message) {
                    if (strpos($file, 'template') !== false) {
                        //Only count new messages once.
                        $this->newMessages++;
                        $this->verbose && print (' Adding: '.$message."\n");
                    }
                    $fileMessages[$message] = '';
                }
                $this->writeMessagesToFile($file, $fileMessages);
            }
        } else {
            if (!isset($this->errors['missingFiles']))
                    $this->errors['missingFiles'] = array();
            $this->errors['missingFiles'][] = $file;
        }
    }

    /**
     * Move commonly used phrases to common.php, second step of automation.
     *
     * Function that parses translation files for all languages and consolidates
     * them. First it builds a list of redundancies between files, then loops
     * through that array, adding redundant phrases to common.php and removing
     * them from their original files. This means any given word/phrase in the
     * software only needs to be translated once. Called in {@link X2TranslationAction::run}
     * function as part of the full translation suite.
     */
    public function consolidateMessages(){
        $this->verbose && print("Consolidating duplicate messages into common...\n");
        $redundancies = $this->buildRedundancyList(); // Get a list of all redundancies between translation files and store it in $this->intersect.
        $this->verbose && print(count($redundancies)." redundancies found.\n");
        for($i = 0; $i < 5 && !empty($redundancies); $i++){ // Keep going until we run out of attempts or there are no more redundant translations.
            foreach($redundancies as $data){
                $first = $data['first']; // Get the name of the first file that has the redundancy
                $second = $data['second']; // Get the name of the second file that has the redundancy
                $messages = $data['messages']; // Get the text of the redundant message.
                foreach($messages as $message){
                    if($first != 'common.php' && $second != 'common.php'){ // If neither of the matched files are common.php
                        $this->verbose && print(' Moving '.$message.' from '.$first.' and '.$second." to common.php\n");
                        $this->addedToCommon++;
                        $this->addToCommon($message); // Add the message to common.php
                    }
                    if($first != 'common.php'){ // Only remove messages from the original files if the file isn't common.php
                        $this->verbose && print(' Removing '.$message.' from '.$first."\n");
                        $this->messagesRemoved++;
                        $this->removeMessage($first, $message);
                    }
                    if($second != 'common.php'){
                        $this->verbose && print(' Removing '.$message.' from '.$second."\n");
                        $this->messagesRemoved++;
                        $this->removeMessage($second, $message);
                    }
                }
            }
            $redundancies = $this->buildRedundancyList(); // Rebuild the redundancy list to be sure there aren't any new redundancies created by the process
        }
        $this->verbose && print("Consolidating duplicate messages complete!\n");
    }
    
    /**
     * Get redundant translations to be merged into common.php
     *
     * Helper function called by {@link consolidateMessages)
     * to build a list of files that have redundant messages in them, as well as a
     * list of what those messages are. Loads this data into the property
     * $this->intersect;
     */
    public function buildRedundancyList(){
        $redundancies = array();
        $files = scandir(Yii::app()->basePath.'/messages/template'); // Only need to check template, not all languages. All languages should mirror template.
        $languageList = array();
        foreach($files as $file){
            if($file != '.' && $file != '..'){
                $languageList[$file] = array_keys(include(Yii::app()->basePath."/messages/template/$file")); // Get the messages from each file in the template folder.
            }
        }
        $keys = array_keys($languageList);
        for($i = 0; $i < count($languageList); $i++){ // Outer loop to check all files in the language list.
            for($j = $i + 1; $j < count($languageList); $j++){ // Inner loop to compare each file against each other file.
                $messages = array_intersect($languageList[$keys[$i]], $languageList[$keys[$j]]); // Calculate the intersection of the messages between each pair of files.
                if(!empty($messages)){ // If we found messages that exist in both, add them to the intersect array to be consolidated.
                    $redundancies[] = array('first' => $keys[$i], 'second' => $keys[$j], 'messages' => $messages);
                }
            }
        }
        return $redundancies;
    }

    /**
     * Add a message to common.php for all languages
     *
     * Helper function called by {@link consolidateMessages}
     * to add a redundant message into common.php. The message will nto be added
     * if it already exists in common.
     *
     * @param string $message The message to be added to common.php
     */
    public function addToCommon($message){
        $languages = $this->getValidLanguagePacks();
        foreach($languages as $lang){
            if($lang != '.' && $lang != '..'){
                $fileName = Yii::app()->basePath.'/messages/'.$lang.'/'.'common.php';
                if(!file_exists($fileName)){ // For some reason common.php doesn't exist for this language.
                    $this->writeMessagesToFile($fileName, array());
                }
                $messages = require $fileName;
                if(!array_key_exists($message, $messages)){
                    $messages[$message] = '';
                    $this->writeMessagesToFile($fileName, $messages);
                }
            }
        }
    }

    /**
     * Deletes a message from a language file in all languages.
     *
     * Called as a part of the consolidation process to remove redundant messages
     * from the files they were found in. This keeps the amount of messages lower
     * and reduced the burden on anyone who is translating the software.
     *
     * @param string $file The name of the file to look for the message in
     * @param string $message The message to be removed
     */
    public function removeMessage($file, $message){
        $languages = $this->getValidLanguagePacks(); // Load all languages.
        foreach($languages as $lang){
            if($lang != '.' && $lang != '..'){
                if(file_exists(Yii::app()->basePath.'/messages/'.$lang.'/'.$file)){
                    $messages = require Yii::app()->basePath.'/messages/'.$lang.'/'.$file;
                    if(isset($messages[$message])){
                        unset($messages[$message]);
                    }
                    $this->writeMessagesToFile(Yii::app()->basePath.'/messages/'.$lang.'/'.$file, $messages);
                }
            }
        }
    }

    /**
     * Call Google Translate API for mising translations, third step of automation.
     *
     * This method will get a list of all messages which do not have translations
     * into the appropriate language from all of our language files. Then, it will
     * call Google Translate's API to get a base translation of the message and
     * insert the translated versions into our translation files. Called in
     * {@link X2TranslationAction::run} function as part of the full translation suite.
     */
    public function updateTranslations(){
        $this->verbose && print("Translating messages via Google Translate API...\n");
        $untranslated = $this->getUntranslatedText(); // Get a list of all messages with missing translations.
        $limit = $this->untranslated; // Set limit to number of expected translations to prevent infinite loops
        $this->verbose && print($this->untranslated." messages need to be translated. Setting limit to ".$this->untranslated.".\n");
        foreach($untranslated as $lang => $langData){
            $this->languageStats[$lang] = 0; // Start tracking stats for this langage.
            foreach($langData as $fileName => $file){
                $translations = array(); // Store translated messages to only do 1 file write per file.
                foreach($file as $index){
                    if($limit >= 0){
                        $limit--;
                        $message = $this->translateMessage($index, $lang); // Translate message for the specified language
                        $translations[$index] = $message; // Store the translation (and original message) to be written to the file later.
                        $this->languageStats[$lang]++;
                    }else{ // We hit our limit
                        $this->replaceTranslations($lang, $fileName, $translations); // Replace translations for what we have now, we'll manually refresh to get more.
                        $this->limitReached = true;
                        break 3; // Break out of all the loops to save time
                    }
                }
                $this->replaceTranslations($lang, $fileName, $translations); // Replace the translated messages into the right file.
            }
        }
        $this->verbose && print("Translating via Google API complete!\n");
    }

    /**
     * Get all untranslated messages
     *
     * Helper function called by {@link updateTranslations}
     * to get an array of all messages which have indeces in the translation files
     * but no translated version.
     *
     * @return array A list of all messages which have missing translations.
     */
    public function getUntranslatedText() {
        $untranslated = array();
        $languages = $this->getValidLanguagePacks();
        foreach ($languages as $lang) {
            if (!in_array($lang, array('template', '.', '..'))) { // Ignore current, parent, and template (all template translations are blank) directories.
                $untranslated[$lang] = array();
                $files = scandir(Yii::app()->basePath . '/messages/' . $lang); // Get all the files for the current language.
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        $untranslated[$lang][$file] = array();
                        $translations = (include(Yii::app()->basePath . '/messages/' . $lang . '/' . $file)); // Include the translations.
                        foreach ($translations as $index => $message) {
                            if (!empty($index) && empty($message)) {
                                $untranslated[$lang][$file][] = $index; // If the translated version is empty, add the message index to our unranslated array.
                                $this->untranslated++;
                            }
                        }
                        if (empty($untranslated[$lang][$file])) {
                            unset($untranslated[$lang][$file]); // If we don't find any untranslated messages, don't both returning that file.
                        }
                    }
                }
                if (empty($untranslated[$lang])) {
                    unset($untranslated[$lang]); // The whole language is translated, no need to return it either.
                }
            }
        }
        return $untranslated;
    }

    /**
     * Translate a message via Google Translate API.
     *
     * Helper function called by {@link updateTranslations}
     * to translate individual messages via the Google Translate API. Any text
     * between braces {} is preserved as is for variable replacement.
     *
     * @param string $message The untranslated message
     * @param string $lang The language to translate to
     * @return string The translated message
     */
    public function translateMessage($message, $lang) {
        $this->verbose && print(" Translating $message to $lang\n");
        $key = require Yii::app()->basePath . '/config/googleApiKey.php'; // Git Ignored file containing the Google API key to store. Ours is not included with public release for security reasons...
        $message = $this->addNoTranslateTags($message);
        $this->characterCount+=mb_strlen($message, 'UTF-8');
        $params = array(
            'key' => $key,
            'source' => 'en',
            'target' => $lang,
            'q' => $message,
        );
        $url = 'https://www.googleapis.com/language/translate/v2?' . http_build_query($params);
        $data = RequestUtil::request(array(
                    'url' => $url,
                    'method' => 'GET',
        ));
        $data = json_decode($data, true); // Response is JSON, need to decode it to an array.
        if (isset($data['data'], $data['data']['translations'],
                        $data['data']['translations'][0],
                        $data['data']['translations'][0]['translatedText'])) {
            $message = $data['data']['translations'][0]['translatedText']; // Make sure the data structure returned is correct, then store the message as the translated version.
        } else {
            $message = ''; // Otherwise, leave the message blank.
        }
        $message = $this->removeNoTranslateTags($message);
        $message = trim($message, '\\/'); // Trim any harmful characters Google Translate may have moved around, like leaving a "\" at the end of the string...
        return $message;
    }
    
    public function addNoTranslateTags($message){
        return preg_replace_callback('/(\{(.*?)\}|<(.*?)>)/', function($matches){
                    return '<span class="notranslate">'.$matches[0].'</span>'; // Replace every instance of text between braces like {text} with <span class="notranslate">{text}</span>. This will make Google Translate ignore that text.
                }, $message);
    }
    
    public function removeNoTranslateTags($message){
        return preg_replace_callback('/'.preg_quote('<span class="notranslate">', '/').'(.*?)'.preg_quote('</span>', '/').'/', function($matches){
                        return $matches[1];
                    }, $message);
    }

    /**
     * Add translated messages to translation files.
     *
     * Helper function called by {@link updateTranslations}
     * to replace the untranslated messages in a translation file with the response
     * we got from Google.
     *
     * @param string $lang The language we translated our messages to
     * @param string $file The file we need to put the translations in
     * @param array $translations An array of translations with the English message as the index and the translated version as the value.
     */
    public function replaceTranslations($lang, $file, $translations){
        $this->verbose && print(" Writing translations to $lang/$file\n");
        $fileName = Yii::app()->basePath.'/messages/'.$lang.'/'.$file;
        if(file_exists($fileName)){
            $messages = require $fileName;
            $messages = array_merge($messages,$translations);
            $this->writeMessagesToFile($fileName, $messages);
        }
    }
    
    public function mergeCustomTranslations() {
        $customDir = str_replace('/protected','/custom/protected',Yii::app()->basePath);
        if (is_dir($customDir . '/messages/')) {
            $customMessages = $customDir . '/messages';
            $customLanguagePacks = array_diff(scandir($customMessages),
                    array('.', '..'));
            foreach ($customLanguagePacks as $dirName) {
                if (is_dir($customMessages . '/' . $dirName)) {
                    $this->mergeCustomLanguagePack($customMessages . '/' . $dirName);
                }
            }
        }
    }

    public function mergeCustomLanguagePack($dir){
        $languageFiles = array_diff(scandir($dir),array('.','..'));
        foreach($languageFiles as $file){
            if(is_file($dir.'/'.$file)){
                $this->mergeCustomTranslationFile($dir.'/'.$file);
            }
        }
    }
    
    public function mergeCustomTranslationFile($file){
        $customMessages = require $file;
        $this->customMessageCount += count($customMessages);
        if(is_array($customMessages) && !empty($customMessages)){
            $baseFile = str_replace('/custom','',$file);
            if(file_exists($baseFile)){
                $defaultMessages = require $baseFile;
                $messages = array_merge($defaultMessages, $customMessages);
                $this->writeMessagesToFile($baseFile, $messages);
            }
        }
    }
    
    public function assimilateLanguageFiles(){
        $languagePackPath = Yii::app()->basePath."/messages";
        $languagePacks = array_diff(scandir($languagePackPath),array('.','..','template'));
        foreach($languagePacks as $languagePack){
            if (is_dir($languagePackPath . '/' . $languagePack)) {
                $this->assimilateLanguagePack($languagePack);
            }
        }
    }
    
    public function assimilateLanguagePack($lang){
        $languagePackPath = Yii::app()->basePath."/messages";
        $languageFiles = array_diff(scandir($languagePackPath . '/' . $lang),array('.','..'));
        foreach($languageFiles as $file){
            if(is_file($languagePackPath . '/' . $lang . '/' . $file)){
                $this->assimilateLanguageFile($lang, $file);
            }
        }
    }
    
    public function assimilateLanguageFile($lang, $file){
        $templateMessages = require Yii::app()->basePath."/messages/template/$file";
        $langMessages = require Yii::app()->basePath."/messages/$lang/$file";
        
        $intersection  = array_intersect_key($langMessages,array_flip(array_keys($templateMessages)));
        
        $this->writeMessagesToFile(Yii::app()->basePath."/messages/$lang/$file", $intersection);
    }
    
    /**
     * Helper function exists in case we change how we write to files again.
     */
    private function writeMessagesToFile($file, $messages){
        file_put_contents($file, '<?php return '.var_export( $messages, true ).";\n"); 
    }
    
    private function getValidLanguagePacks() {
        $languageDirs = scandir(Yii::app()->basePath . '/messages/'); // scan for installed language folders
        $languages = array();
        foreach ($languageDirs as $code) {
            if ($this->isValidLanguagePack($code)) {
                $languages[] = $code;
            }
        }
        return $languages;
    }

    private function isValidLanguagePack($code) { // lookup language name for the language code provided
        $appMessageFile = Yii::app()->basePath . "/messages/$code/app.php";
        if (file_exists($appMessageFile)) { // attempt to load 'app' messages in
            $appMessages = include($appMessageFile);     // the chosen language
            return is_array($appMessages) && isset($appMessages['languageName']);
        }
        return false;
    }

}
