#!/usr/bin/env python

#------------------------------------------------
# zip2s3.py
#
# Author: Bernie Zimmermann
#
# Dependencies:
#
# boto:
# https://github.com/boto/boto
#------------------------------------------------

from boto.s3.connection import S3Connection
from boto.s3.key import Key
import cStringIO
import zipfile

# defines
AWS_ACCESS_KEY = 'my_access_key'
AWS_SECRET_KEY = 'my_secret_key'
AWS_BUCKET = 'my_bucket'

# use a file-like object for the zip file
obj = cStringIO.StringIO()

# open the zip file
zip_file = zipfile.ZipFile(obj, 'w', compression=zipfile.ZIP_DEFLATED)

# compress several files in the zip file
zip_file.writestr('a.txt', 'this is the a file')
zip_file.writestr('b.txt', 'this is the b file')
zip_file.writestr('c.txt', 'this is the c file')

# close the zip file
zip_file.close()

# seek to the beginning of the file-like object
obj.seek(0)

# connect to S3
s3 = S3Connection(AWS_ACCESS_KEY, AWS_SECRET_KEY)

# get the bucket
bucket = s3.get_bucket(AWS_BUCKET)

# get a new key for the bucket
k = Key(bucket)

# set the name of the key
k.key = 'my_zip_file.zip'

# store the zip file on S3
k.set_contents_from_file(obj)

# close the file-like object
obj.close()

