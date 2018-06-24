#! /usr/bin/env python
#
# author: Paweł Archacki - pbl@IRCNet
# mail: 	  archacki85@gmail.com
# License GPLv2

import argparse
import sys
import jaraco.logging
import irc.client
import requests
import pprint
import time
import re
from time import gmtime, strftime
from netaddr import *
from bs4 import BeautifulSoup

#Time beetwen querys - 40 second is a good TIME. 
global timeSleep
timeSleep = 40

# Main
global failsCount, listPosition, PATH, isListRequired
isListRequired = True
listPosition =0 
failsCount = 0
PATH = '/home/iline/ilinebot/db'
IRCServersList = []

def getList():

	isListRequired = False

	try:
		r = requests.get('http://irc.tu-ilmenau.de/all_servers/')
		soup = BeautifulSoup(r.content, "html.parser")
		i =0
		for link in soup.find_all('a', href = re.compile(r'.*details.cgi*')):
			s = str(link.get_text().lower())
			if (str(s.split('.')[0]) != 'hub' and str(s.split('.')[0]) != 'irc-2112p3'):
				match = re.search(r'\]', s)
				if match:
					s = s.split(' ')[0]
				IRCServersList.insert(i, s)
				i +=1
		for x in IRCServersList:
			print (x)

	except:
		sys.exit(1)

def scriptEnd(connection):
	IPv4_FILE.close()
	IPv6_FILE.close()
	STATUS_FILE.close()
	ERROR_FILE.close()
	connection.quit("Leaving")
	print ("Script properly end")
	sys.exit(0)

def writeData(srvName, ipNet, ipBroad, ipMask, cctld, tld, protocol):

	#print ("DEBUG :" + serverName + "|" + ipNet +"|" + ipBroad + "|" + ipMask + "|" + cctld + "|" + tld + "\n")

	if protocol == "IPv4":
		IPv4_FILE.write(serverName + "|" + ipNet +"|" + ipBroad + "|" + ipMask + "|" + cctld + "|" + tld + "\n")

	if protocol == "IPv6":
		IPv6_FILE.write(serverName + "|" + ipNet +"|" + ipBroad + "|" + ipMask + "|" + cctld + "|" + tld + "\n")

def getCcTLD(srvName):
	global serverName
	ccTLD= serverName.split('.')[-1]
	return ccTLD.upper()

def getTLD(srvName):
	global serverName
	TLD = serverName.split('.')[-2]
	return TLD

def parser_ipv4(IPv4):
	global serverName

	if IPv4.size > 2:
		
		ipMask = str(IPv4)
		ipNetwork = str(int(IPv4.network))
		ipBroadcast = str(int(IPv4.broadcast))
		writeData(serverName,  ipNetwork, ipBroadcast, ipMask, getCcTLD(serverName), getTLD(serverName), "IPv4")

	else:
		
		ipMask = str(IPv4)
		ipNetwork = str(int(IPv4.network))
		writeData(serverName,  ipNetwork, ipNetwork, ipMask, getCcTLD(serverName), getTLD(serverName), "IPv4")

def parser_ipv6(IPv6):
	global serverName

	if IPv6.size > 2:
	
		ipMask = str(IPv6)
		ipNetwork = str(int(IPv6.network))
		ipBroadcast = str(int(IPv6.broadcast))
		writeData(serverName,  ipNetwork, ipBroadcast, ipMask, getCcTLD(serverName), getTLD(serverName), "IPv6")

	else:
		
		ipMask = str(IPv6)
		ipNetwork = str(int(IPv6.network))
		writeData(serverName,  ipNetwork, ipNetwork, ipMask, getCcTLD(serverName), getTLD(serverName), "IPv6")

def on_statsiline(connection, event):
	
	ipToParse = event.arguments[1]
	ipToParse = ipToParse.replace("*", "")
	ipToParse = ipToParse.replace("@", "")
	global serverName, failsCount

	try:
		IPNet = IPNetwork(ipToParse)

		if IPNet.version == 4:
			
			parser_ipv4(IPNet)
			
		elif IPNet.version == 6:
			
			parser_ipv6(IPNet)

	except AddrFormatError:
		ERROR_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + " ERROR: netaddr.core.AddrFormatError: invalid IPNetwork -> " + ipToParse + "\n")
			
def on_endofstats(connection, event):

	sys.stdout.flush()
	global serverName, listSizeMax, listPosition
	listPosition +=1
	
	if listPosition == listSizeMax:
	
		scriptEnd(connection)

	print ("Waiting for next query...")
	time.sleep(timeSleep)

	try:
		serverName = IRCServersList[listPosition]
		print("Getting list from: " + serverName + " [ " + str(listPosition+1) + " / " + str(listSizeMax) + " ]")
		connection.stats("I",IRCServersList[listPosition])
	except:
		ERROR_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + " ERROR: Something went wrong while trying to get i line list from: " + serverName + "\n")

def on_connect(connection, event):
	global listPosition, failsCount
	sys.stdout.flush()
	
	try:
		serverName = IRCServersList[listPosition]
		print("Getting list from: " + serverName + " [ " + str(listPosition+1) + " / " + str(listSizeMax) + " ]")
		connection.stats("I",IRCServersList[listPosition])

	except:
		ERROR_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + " ERROR: Something went wrong while trying to get i line list from: " + serverName + "\n")

def on_nosuchserver(connection, event):
	global listPosition
	sys.stdout.flush()
	print("No such server: " + serverName)
	ERROR_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + " ERROR: No such server: " + serverName + "\n")
	on_endofstats(connection, event)

def on_tryagain(connection, event):
	global listPosition, failsCount
	sys.stdout.flush()
	listPosition -=1
	failsCount +=1
	print ("FAILS COUNT " + str(failsCount))
	print("IRC server is busy, waiting...: " + serverName)

	if failsCount > 7:
		STATUS_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + "CRITICAL: Can not get list from: " + serverName + "\n")
		failsCount =0
		listPosition +=1
	
	on_endofstats(connection, event)	

def on_disconnect(connection, event):
	print("Connection reset from remote server: " + serverName)
	sys.stdout.flush()
	global failsCount
	
	failsCount +=1
		
	if failsCount > 7:
		STATUS_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + "CRITICAL: Can not get list from: " + serverName + "\n")
		failsCount =0
		listPosition +=1
		
	print("global failsCount" + str(failsCount))	
	print("Retry on: " + serverName)
	main()

def get_args():
	parser = argparse.ArgumentParser()
	parser.add_argument('server')
	parser.add_argument('nickname')
	parser.add_argument('-p', '--port', default=6667, type=int)
	jaraco.logging.add_arguments(parser)
	return parser.parse_args()	

def main():
	
	global IPv4_FILE, IPv6_FILE, ERROR_FILE, STATUS_FILE, serverName, listPosition, listSizeMax, failsCount, isListRequired
	
	if isListRequired:
		try:
			getList()
		except:
			print("Can not pares list from the website")
			sys.exit(1)

	listSizeMax = len(IRCServersList)
	IPv4_FILE = open(PATH + "/IPV4_FILE.db", "a")
	IPv6_FILE = open(PATH + "/IPV6_FILE.db", "a")
	ERROR_FILE = open(PATH + "/error.log", "a")
	STATUS_FILE = open(PATH + "/status.log", "a")
	serverName = IRCServersList[listPosition]
	args = get_args()
	jaraco.logging.setup(args)
	reactor = irc.client.Reactor()
	sys.stdout.write("Connecting to server...\n")
	sys.stdout.flush()
	try:
		c = reactor.server().connect(args.server, args.port, args.nickname)
	except irc.client.ServerConnectionError as x:
		print(x)
		sys.exit(1)

	c.add_global_handler("welcome", on_connect)
	c.add_global_handler("statsiline", on_statsiline)
	c.add_global_handler("nosuchserver", on_nosuchserver)
	c.add_global_handler("tryagain", on_tryagain)
	c.add_global_handler("endofstats", on_endofstats)
	c.add_global_handler("disconnect", on_disconnect)

	reactor.process_forever()
	
if __name__ == '__main__':
	main()
