from rauth.service import OAuth1Service

#
# Note: at the time of implementation, this has a dependency on an
# older version of the Requests module, which can be obtained by
# downgrading like so:
#
# sudo pip install --upgrade requests=0.14.2
#

# set up the Tumblr API OAuth 1.0a request
tumblr = OAuth1Service(
    name='tumblr',
    consumer_key='MY_TUMBLR_CONSUMER_KEY',
    consumer_secret='MY_TUMBLR_CONSUMER_SECRET',
    request_token_url='http://www.tumblr.com/oauth/request_token',
    access_token_url='http://www.tumblr.com/oauth/access_token',
    authorize_url='http://www.tumblr.com/oauth/authorize',
    header_auth=True)

# get a request token and secret
request_token, request_token_secret = tumblr.get_request_token(method='GET')

# go through the authentication flow
authorize_url = tumblr.get_authorize_url(request_token)
print 'Visit this URL in your browser: ' + authorize_url
pin = raw_input('Enter oauth_verifier query parameter from browser: ')

# get the access token
response = tumblr.get_access_token('GET',
    request_token=request_token,
    request_token_secret=request_token_secret,
    params={'oauth_verifier': pin})

# store the response content
data = response.content

# store the access token and secret
access_token = data['oauth_token']
access_token_secret = data['oauth_token_secret']

# print the access token and secret
print 'Access token: %s' % (access_token)
print 'Access token secret: %s' % (access_token_secret)
