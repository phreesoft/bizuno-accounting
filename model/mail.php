<?php
/*
 * This class is a wrapper to PHPMailer to handle bizuno messaging
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2024, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2023-05-03
 * @filesource /model/mail.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'model/portal.php', 'portalMail');

class bizunoMailer extends portalMail
{
    /**
     * Prepares the mail transport to send emails.
     * @param mixed  $toEmail - email addresses, can be array, separated with comma or semi-colons
     * @param string $toName - Textual recipient
     * @param string $subject - The subject for the email, null is allowed to leave subject blank
     * @param string $body - The HTML body for the email, null is allowed to leave body blank
     * @param mixed  $fromEmail - [default: user email] email addresses, can be array, separated with comma or semi-colons
     * @param string $fromName - [default: user title] Textual sender name
     */
    public function __construct($toEmail='', $toName='', $subject='', $body='', $fromEmail='', $fromName='')
    {
        if     (sizeof($results = explode(',', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toEmail[] = ['email'=>trim($email), 'name'=>$toName]; } }
        elseif (sizeof($results = explode(';', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toEmail[] = ['email'=>trim($email), 'name'=>$toName]; } }
        else { $this->toEmail[] = ['email'=>trim($toEmail), 'name'=>$toName]; }
        $this->ToName    = $toName;
        $this->Subject   = $subject;
        $this->Body      = $body;
        $this->FromEmail = $fromEmail ? $fromEmail : getUserCache('profile', 'email');
        $this->FromName  = $fromName  ? $fromName  : getUserCache('profile', 'title');
        $this->toCC      = [];
        $this->attach    = [];
        msgDebug("\nSending to: $toName email: $toEmail sub: $subject body: $body from: $fromName email: $fromEmail");
    }

    /**
     * Adds one or more CC's to the email
     * @param mixed $toEmail - email addresses, can be array, separated with comma or semi-colons
     * @param string $toName - Textual recipient
     */
    public function addToCC($toEmail, $toName='')
    {
        if       (sizeof($results = explode(',', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toCC[] = ['email'=>$email, 'name'=>'']; }
        } elseif (sizeof($results = explode(';', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toCC[] = ['email'=>$email, 'name'=>'']; }
        } else                           { $this->toCC[] = ['email'=>$toEmail, 'name'=>$toName]; }
    }

    /**
     * Adds an attachment to the email
     * @param string $path - full path of the attachment file
     * @param string $name - name to be assigned to the file, leave null to use file system name
     */
    public function attach($path, $name='') {
        $this->attach[] = ['path'=>$path, 'name'=>$name];
    }
}
