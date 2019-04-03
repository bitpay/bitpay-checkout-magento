#!/usr/bin/env sh
#
# (c) 2014 BitPay, Inc.
#
# Shell script that locates and removes
# any previously installed BitPay Magento
# plugin files.
#
# Written by Rich Morgan <rich@bitpay.com>

# For full transparency I've listed all the depricated files and folders
# related to the old plugin here for your information. This is the complete
# list of items you would want to backup and/or remove if you wanted to do
# this by hand yourself or if you just wanted a list for tracking purposes.
old_files[0]="/lib/bitpay/bp_config_default.php"
old_files[1]="/lib/bitpay/bp_lib.php"
old_files[2]="/lib/bitpay/bp_options.php"
old_files[3]="/lib/bitpay"
old_files[4]="/app/design/frontend/base/default/template/bitcoins/iframe.phtml"
old_files[5]="/app/design/frontend/base/default/template/bitcoins"
old_files[6]="/app/design/frontend/base/default/layout/bitcoins.xml"
old_files[7]="/app/code/community/Bitpay/Bitcoins/Model/Resource/Ipn/Collection.php"
old_files[8]="/app/code/community/Bitpay/Bitcoins/Model/Resource/Ipn"
old_files[9]="/app/code/community/Bitpay/Bitcoins/Model/Resource/Ipn.php"
old_files[10]="/app/code/community/Bitpay/Bitcoins/Model/PaymentMethod.php"
old_files[11]="/app/code/community/Bitpay/Bitcoins/Model/Ipn.php"
old_files[12]="/app/code/community/Bitpay/Bitcoins/Model/Source/Speed.php"
old_files[13]="/app/code/community/Bitpay/Bitcoins/Model/Source"
old_files[14]="/app/code/community/Bitpay/Bitcoins/Model/Resource"
old_files[15]="/app/code/community/Bitpay/Bitcoins/Model"
old_files[16]="/app/code/community/Bitpay/Bitcoins/sql/Bitcoins_setup/upgrade-0.1.0-1.0.0.php"
old_files[17]="/app/code/community/Bitpay/Bitcoins/sql/Bitcoins_setup/upgrade-1.0.0-1.1.0.php"
old_files[18]="/app/code/community/Bitpay/Bitcoins/sql/Bitcoins_setup"
old_files[19]="/app/code/community/Bitpay/Bitcoins/sql"
old_files[20]="/app/code/community/Bitpay/Bitcoins/Block/Iframe.php"
old_files[21]="/app/code/community/Bitpay/Bitcoins/Block"
old_files[22]="/app/code/community/Bitpay/Bitcoins/controllers/IndexController.php"
old_files[23]="/app/code/community/Bitpay/Bitcoins/controllers"
old_files[24]="/app/code/community/Bitpay/Bitcoins/etc/config.xml"
old_files[25]="/app/code/community/Bitpay/Bitcoins/etc/system.xml"
old_files[26]="/app/code/community/Bitpay/Bitcoins/etc"
old_files[27]="/app/code/community/Bitpay/Bitcoins"
old_files[28]="/app/code/community/Bitpay"
old_files[29]="/app/etc/modules/Bitpay_Bitcoins.xml"
old_files[30]="composer.json"
old_files[31]="magento-plugin-master.zip"
old_files[32]="modman"
old_files[33]="README.md"

CLEAN="true"
RMOPTS="-vrfd"

echo "Looking for your Magento installation. Please stand by - this may take a few minutes while I search..."

# In case we have multiple Magento installs on this one
# server, we will just take the first one and ask...
i=`find /var /usr /opt -name Mage.php -type f | head -n 1 2>/dev/null`

if [ -e $i ]
then
    DIR=`dirname $i`
    cd $DIR && cd ../
    mage_dir=`pwd`
    echo "It looks like Magento is installed in the $mage_dir directory."
    echo "Is this correct? (y/n) > "
else
    # In case we can't find the Magento folder, we are just
    # providing a default value here. This happens to be the
    # default for an Ubuntu-based machine, for example.
    DIR="/var/www/html/magento"
    cd $DIR
    mage_dir=`pwd`
    echo "You don't have Magento installed or this script doesn't have permissions to view the directory it's contained in."
    echo "Should I default to $mage_dir ? (y/n) > "
fi

read answer

if [ $answer = "y" ]
then
    echo "Attempting to delete the old plugin files now. Please stand by..."
    for filename in "${old_files[@]}"
    do
        fullname=$mage_dir$filename
        if [ -e $fullname ]
        then
            echo "  Found $fullname - removing!"
            rm $RMOPTS $fullname
        else
            echo "  $filename does not exist - skipping!"
        fi
    done
    echo "File removal process complete. Checking to make sure your Magento environment was completely cleaned of old BitPay files..."
    echo ""
    for filename in "${old_files[@]}"
    do
        fullname=$mage_dir$filename
        if [ -e $fullname ]
        then
            echo "  The old plugin file $fullname is still present!"
            CLEAN="false"
        else
            echo "  $filename is not present - good!"
        fi
    done
    if [ $CLEAN = "false" ]
    then
        echo "Old BitPay plugin files are still present in your Magento directory. This is likely due to this script not having permissions to delete them. You can fix this by running this script as superuser or you can remove the files by hand."
    else
        echo "Good!  I didn't find any remaining old BitPay plugin files in your Magento directory!  You can now safely install the new BitPay plugin."
    fi
    echo "Process complete."
    echo ""
else
    echo "Okay, please enter the path you would like me to use or QUIT if you wish to abort the process."
    echo "Full path or QUIT ? > "
    read answer
    if [ $answer = "QUIT" ]
    then
        echo "Quitting!"
    else
        echo "Attempting to delete the old plugin files at the directory you provided. Please stand by..."
        for filename in "${old_files[@]}"
        do
            fullname=$answer$filename
            if [ -e $fullname ]
            then
                echo "  Found $fullname - removing!"
                rm $RMOPTS $fullname
            else
                echo "  $filename does not exist - skipping!"
            fi
        done
        echo "File removal process complete. Checking to make sure your Magento environment was completely cleaned of old BitPay files..."
        echo ""
        for filename in "${old_files[@]}"
        do
            fullname=$mage_dir$filename
            if [ -e $fullname ]
            then
               echo "  The old plugin file $fullname is still present!"
                CLEAN="false"
            else
                echo "  $filename is not present - good!"
            fi
        done
        if [ $CLEAN = "false" ]
        then
            echo "Old BitPay plugin files are still present in your Magento directory. This is likely due to this script not having permissions to delete them. You can fix this by running this script as superuser or you can remove the files by hand."
        else
            echo "Good!  I didn't find any remaining old BitPay plugin files in your Magento directory!  You can now safely install the new BitPay plugin."
        fi
        echo "Process complete."
        echo ""
    fi
fi
