# Magento 2 SMTP Extension - Gmail, Amazon SES, Mailgun, SendGrid, Mandrill

Every day you send and receive so many even more hundreds of emails, however, you actually do not know whether they come to your targeted customers or not. Therefore, **Magento 2 SMTP Extension** is come out as the solution for this problem.

**SMTP Extension for Magento 2** helps the owner of store simply install **SMTP (Simple Mail Transfer Protocol)** server which transmits the messages into codes or numbers. Through it, messages will be delivered directly and automatically to the chosen customers. Moreover, it is also flexible configurations with 21 different *SMTP servers* such as `Gmail, Hotmail, O2 Mail, Office365, Mail.com, Send In Blue, AOL Mail Orange, GMX, Outlook, Yahoo, Comcast, or Custom SMTP` - own SMTP server, etc. 

[![Latest Stable Version](https://poser.pugx.org/mageplaza/module-smtp/v/stable)](https://packagist.org/packages/mageplaza/module-smtp)
[![Total Downloads](https://poser.pugx.org/mageplaza/module-smtp/downloads)](https://packagist.org/packages/mageplaza/module-smtp)

![smtp configuration](https://i.imgur.com/GoI1Y7U.png)


## 1. Documentation

- [Installation guide](https://www.mageplaza.com/install-magento-2-extension/)
- [User Guide](https://www.mageplaza.com/magento-2-smtp/user-guide.html)
- [Download from our Live site](https://www.mageplaza.com/magento-2-smtp/)
- [Get Free Support](https://github.com/mageplaza/magento-2-smtp/issues)
- Get premium support from Mageplaza: [Purchase Support package](https://www.mageplaza.com/magento-2-extension-support-package/)
- [Contribute on Github](https://github.com/mageplaza/magento-2-smtp)
- [Releases](https://github.com/mageplaza/magento-2-smtp/releases)
- [License](https://www.mageplaza.com/LICENSE.txt)



## 2. How to install SMTP Extension

### Install via composer (recommend)

Run the following command in Magento 2 root folder:

```
composer require mageplaza/module-smtp
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```


## 3. Highlight features:

### Flexible Configurations

It is easy for the store owner to reset the data or the information of any attributes with many options. Depending on your purposes, Mails will be configured simply and much faster.

### SMTP Debug mode

Before the emails are sent, they will be tested by running the test email from this extension to be sure that emails exact content is sent to desired customers. If there is any mistake, the emails will be logged them to correct the errors but we are not necessary to recheck all the settings.
Moreover, by this **Debug mode**, owners can manage, preview or review the time the email created. You are also able to delete the logging or it can be done automatically after a period of time.

### Email logging

All the emails sent out from your store will be kept in this log on **Magento 2 SMTP extension**. The Admin totally can recheck the content of the email and it was sent to whom. Furthermore, you also check the time sent and the current status whether it is pending, in process or failed in the list. Especially, you can clear the log of the mail like the debug mode, manually or let it be after a certain time.

### Test email

This feature allows admin test the [SMTP](https://github.com/magento-2/smtp) Settings and make sure it works for current store.

## Full features of the SMTP Extension for Magento 2

- Use Your Own SMTP Server 
- Sending the test emails before sending officially
- Ensure all emails will be sent to desired customers 
- Email logging with detailed view of every letter
- Delete email log manually
- Debug mode by SMTP email settings to test
- Supports flexible servers
- Check and preview sent emails




## 4. User Guide


In Magento 2, default email server of inherent hosting will be used to send unregistered emails, which means the reputation for this email is pretty low and they will be rated as untrustworthy content, as a matter of fact your precious emails will be delivered to spamming box without knocking up any notification to receiver. Imagine how enormous detriment your business is having when you couldn’t properly send such Order Confirmation, Invoice, Shipment Information,... to their inbox, but classified as spam trash and this is not a professional management.

**Mageplaza SMTP** will assist you to resolve this issue. By available popular email server providers, our extension absolutely would like to help you in sending email with a huge amount of quantity, faster along with high secure authentication. Hence, Mageplaza SMTP will also provide you a log diary which archive all the detail sent emails, makes it easier to keep track and checking problems. Be ready to say goodbye to Spam box issue.


Here we go how to know detail in instructions and configuration in extension’s backend.

### How to config SMTP

#### 1. Email logs

This can be accessed by the following  `Mageplaza > SMTP > Email Logs`. From here you can look back all the sent email from the server to customers.

![How to config SMTP Email logs](https://i.imgur.com/k5KfDLL.png)

By clicking View in each mail, you can have a general looking at the display which how your email will reach customer’s eyes.
Hit the Clear red button to clear all the archived emails after checking carefully.

![ How to config SMTP order](https://i.imgur.com/5eos9R7.png)

#### 2. Configuration

##### 2.2.1 General Configuration

Be sure you’re at Admin Panel, for general configuration `Mageplaza > SMTP > Configuration > General Configuration`

Choose Yes to enable Mageplaza SMTP on.

![ How to config SMTPSMTP on](http://i.imgur.com/4jN9BIx.png)

##### 2.2.2 SMTP Configuration Options
Still from the same structure with General Configuration, scroll down to see  SMTP Configuration Options

![magento 2 smtp configuration](https://i.imgur.com/VnCM6SB.png)

- In SMTP Provider field, at the moment we support provider nearly 30 SMTP email service providers so feel free to choose your appropriate provider. Click Auto Fill button to fill Host, Port, Authentication and  Protocol automatically, which are compatible with the SMTP provider you had chosen. 

- At Host field, type your Support Host name and ID Address. You can also custom STMP Provider’s Host name at here. If you had clicked Auto fill button at the above field, you can give this step a free pass.

- Port is a specific gate where emails will be sent through. You can also pass this step if you had choose Auto fill from the first place. In general, there will be 3 kinds of Default Port

	- Port 25: Emails sent by other Protocol which different SSL will be sent through this portal
	- Port 465: Emails sent by other Protocol SSL will be sent through this portal
	- Port 578: Emails sent by other Protocol TLS will be sent through this portal

- Authentication field is place where  you decide an authentication method. If you hadn’t clicked Auto fill button before, please note those basic methods
	- Login: Authentication by login to the account through Username and Password that will be filled in the next field. Most of provider will require this method.
	- Plain
	- CRAM-MD5
- Account: where you enter the account name matching format of the SMTP Provider you had selected
- Password: password of the Username. After saving, the password will be encrypted into ******
- Protocol: pass this step if you had chosen Auto fill, or you can select one of the providing protocol below here
	- None: when you select this protocol, you have to accept all the risk may occur in the process of sending.
	- SSL stands for Secure Socket Layer. This protocol ensures that all data exchanged between the web server and the browser is secure and stay safe.
	- TLS means Transport Layer Security. This protocol secures data or messages and validates the integrity of messages through message authentication codes.
- Return-path email: leave it empty if you want to ignore this.
- Test email recipient: This is the field for you to test the operation of the extension. After filling all fields, click Test Now button. If the information entered is valid, a successful email notification will be sent from Username to Email Test. That email will have the following content:

![magento 2 smtp test result](https://i.imgur.com/D0cw3ta.png)


##### 2.2.3 Schedule Log Cleaner

This section is placed right under SMTP Configuration Options, which is from Admin Panel > Mageplaza > SMTP > Configuration > scroll down and expand to see Schedule Log Cleaner

![smtp cleaner](https://i.imgur.com/lK28kKF.png)

The Clean Email Log Every field limits the storage time for the email you sent. After that limited number of days, Email will automatically delete. If you do not want to delete the emails, leave the field blank.


#### 2.2.4 Developer 

- Log Email will supply two modes:
	- Yes: Sent emails will be saved in the Emails Log, you can preview it and having it clean up follow fixed schedule.
	- No: Sent emails won’t be archived.

- Developer Mode:
	- Yes: Magento will not deliver any email to receiver
	- No: Magento will deliver email to receiver






**Mageplaza extensions on Magento Marketplace, Github**


☞ [Magento 2 One Step Checkout extension](https://marketplace.magento.com/mageplaza-magento-2-one-step-checkout-extension.html)

☞ [Magento 2 Blog extension Free](https://github.com/mageplaza/magento-2-blog)

☞ [Magento 2 Layered Navigation Ultimate](https://www.mageplaza.com/magento-2-layered-navigation-extension/)

☞ [Magento 2 Blog FREE](https://github.com/mageplaza/magento-2-blog)

☞ [Magento 2 Social Login FREE](https://github.com/mageplaza/magento-2-social-login)

☞ [Magento 2 SEO Suite FREE](https://github.com/mageplaza/magento-2-seo)

☞ [Magento 2 Layered Navigation Free](https://github.com/mageplaza/magento-2-ajax-layered-navigation)

☞ [Magento 2 SMTP FREE](https://github.com/mageplaza/magento-2-smtp) 

☞ [Magento 2 Product Slider FREE](https://github.com/mageplaza/magento-2-product-slider)

☞ [Magento 2 Slider FREE](https://github.com/mageplaza/magento-2-banner-slider)


**People also search:**
- mageplaza smtp
- mageplaza smtp magento 2
- smtp magento 2
- magento 2 smtp extension
- magento 2 smtp settings
- smtp pro magento 2
- mageplaza/module-smtp
- magento 2.3 smtp
- magento 2 smtp configuration

