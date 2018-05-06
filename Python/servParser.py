# -*- coding: utf-8 -*-
import argparse
import sys
import jaraco.logging
import irc.client
import requests
import os
import pprint
import time
import re
from netaddr import *
from bs4 import BeautifulSoup


try:
	r = requests.get('http://irc.tu-ilmenau.de/all_servers/')
	soup = BeautifulSoup(r.content, "html.parser")
	
	for link in soup.find_all('a', href = re.compile(r'.*details.cgi*')):
		if (link.get_text().lower().split('.')[0]) != 'hub':
			print (link.get_text().lower())
	
except:
		exit()