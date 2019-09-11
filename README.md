# woocommerce

Take various payments methods on your store using Red Dot Payment.

## Installation 

1. WooCommerce Store // update

    a. Go to Plugins > Add New on the WordPress Dashboard.
    b. Search for Red Dot Payment WooCommerce in the Search Plugin bar. 
    c. Click "Install Now" and "Activate"
    c. The Plugin is now successfully installed.

2. Download the zip file from github //update

    a. Go to Plugins > Add New on the WordPress Dashboard.
    b. Click "upload Plugin" button at the top of the page.
    c. Upload the zip file that you have downloaded and Click "Install Now".
    d. Once the plugin is installed, Click on the "Activate Plugin" button to activate the plugin.
    e. The Plugin is now successfully installed.

## Setup and Configuration

1. Go to: WooCommerce > Settings > Payments > Red Dot Payment
2. When first activated, Red Dot Payment method will be Enabled. Tick the Enable Red Dot Payment to disable it
3. Enter a Title and Description.
    - Title is shown at the payment method option on the Checkout Page and within the Order showing how the customer paid.
    - Description is displayed within the payment method option on the Checkout Page.
4. By Default, the Test Mode methods is enabled upon installation. Untick the Enable Test Mode checkbox to begin accepting payment with Red Dot Payment.
5. Enter your Client Key, Client Secret and Merchant Id that was generated on the Hosted Page Admin.

## WebHooks

WebHooks are a way for Red Dot Payment to update order's transaction status after customer completes transaction.
It is highly recommended to setup on your Hosted Page Admin Dashboard. 

1. Go to WooCommerce > Settings > Payments > Red Dot Payment and you will see a generated webHook link. 
2. Copy the link and paste it to the callback url input field. 
3. Save Changes.



