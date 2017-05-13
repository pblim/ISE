#! /usr/bin/env python
#
# author: Paweł Archacki - pbl@IRCNet
# mail: 	  archacki85@gmail.com
# License GPLv2

import argparse
import sys
import jaraco.logging
import irc.client
from netaddr import *
import pprint
import time
from time import gmtime, strftime
	
# https://pythonhosted.org/netaddr/tutorial_01.html

#IRCServersList = ['irc.snt.ipv6.utwente.nl','irc.snt.utwente.nl','openirc.snt.utwente.nl','irc.powertech.no','irc.ifi.uio.no','irc.home.uit.no','ircnet.underworld.no','irc.dotsrc.org','poznan.irc.pl','irc.okit.se','irc.portlane.se','irc.swipnet.se','irc.arnes.si','ircd.seed.net.tw']

# List of irc servers excluded hubs
IRCServersList = ['vienna.irc.at','ircnet.clue.be','irc.ipv6.cesnet.cz','irc.felk.cvut.cz','irc.nfx.cz','belwue.de','fu-berlin.de','MAN-DA.DE','TUM.DE','uni-erlangen.de','irc.datanet.ee','irc.starman.ee','irc.elisa.fi','irc.cs.hut.fi','irc2.inet.fi','irc.lut.fi','irc.nebula.fi','irc.opoy.fi','irc.oulu.fi','hub.cc.tut.fi','irc.cc.tut.fi','ircnet.nerim.fr','atw.irc.hu','ssl.atw.irc.hu','irc.fast.net.il','irc1.tiscali.it','ircd.tiscali.it','javairc.tiscali.it','javairc2.tiscali.it','irc6.tophost.it','irc.media.kyoto-u.ac.jp','irc.livedoor.ne.jp','irc.atw-inter.net','ssl.irc.atw-inter.net','eu.irc6.net','eris.us.ircnet.net','irc.us.ircnet.net','irc.nlnog.net','irc.snt.ipv6.utwente.nl','irc.snt.utwente.nl','openirc.snt.utwente.nl','irc.powertech.no','irc.ifi.uio.no','irc.home.uit.no','ircnet.underworld.no','irc.dotsrc.org','poznan.irc.pl','irc.okit.se','irc.portlane.se','irc.swipnet.se','irc.arnes.si','ircd.seed.net.tw']

#Time beetwen querys - 40 second is a good option. 
global timeSleep
timeSleep = 40



# Main
global failsCount
failsCount =0

def parser_ipv4(IPv4):
	global serverName

	if IPv4.size > 2:
		
		ipMask = str(IPv4)
		ipNetwork = str(int(IPv4.network))
		ipBroadcast = str(int(IPv4.broadcast))
		IPv4_FILE.write(serverName + "|" + ipNetwork +"|" + ipBroadcast + "|" + ipMask + "\n")

	else:
		
		ipMask = str(IPv4)
		ipNetwork = str(int(IPv4.network))
		IPv4_FILE.write(serverName + "|" + ipNetwork +"|" + ipNetwork + "|" + ipMask + "\n")
	

def parser_ipv6(IPv6):
	global serverName

	if IPv6.size > 2:
	
		ipMask = str(IPv6)
		ipNetwork = str(int(IPv6.network))
		ipBroadcast = str(int(IPv6.broadcast))
		IPv6_FILE.write(serverName + "|" + ipNetwork +"|" + ipBroadcast + "|" + ipMask + "\n")
	
	else:
		
		ipMask = str(IPv6)
		ipNetwork = str(int(IPv6.network))
		IPv6_FILE.write(serverName + "|" + ipNetwork +"|" + ipNetwork + "|" + ipMask + "\n")
	
	

def on_statsiline(connection, event):
	
	ipToParse = event.arguments[1]
	ipToParse = ipToParse.replace("*", "")
	ipToParse = ipToParse.replace("@", "")
	global serverName
	global failsCount
	
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
	global serverName
	global listPosition
	global listSizeMax
	global failsCount
	listPosition +=1
	
	if listPosition == listSizeMax:
	
		connection.quit("Leaving")
		IPv4_FILE.close()
		IPv6_FILE.close()
		ERROR_FILE.close()
		STATUS_FILE.close()
		print ("Script properly end")
		sys.exit(0)
	
	print ("Waiting for next query...")
	time.sleep(40)
	
	try:
		serverName = IRCServersList[listPosition]
		print("Getting list from: " + serverName + " [ " + str(listPosition+1) + " / " + str(listSizeMax) + " ]")
		connection.stats("I",IRCServersList[listPosition])
		
	except:
		ERROR_FILE.write(strftime("%Y-%m-%d %H:%M:%S", gmtime()) + " ERROR: Something went wrong while trying to get i line list from: " + serverName + "\n")

	
def on_connect(connection, event):
	global listPosition
	global failsCount
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
	global listPosition
	sys.stdout.flush()
	listPosition -=1
	print("IRC server is busy, waiting...: " + serverName)
	
	on_endofstats(connection, event)	
		
def on_disconnect(connection, event):
	print("Connection reset from remote server: " + serverName)
	sys.stdout.write("Connecting to server...\n")
	sys.stdout.flush()
	global failsCount
	
	failsCount +=1
	
	if failsCount > 5:
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

	global IPv4_FILE
	global IPv6_FILE
	global ERROR_FILE
	global STATUS_FILE
	global serverName
	global listPosition
	global listSizeMax
	global failsCount
	
	listSizeMax = len(IRCServersList)
	listPosition =0 
	
	
	IPv4_FILE = open("./db/IPV4_FILE.db", "a")
	IPv6_FILE = open("./db/IPV6_FILE.db", "a")
	ERROR_FILE = open("./db/error.log", "a")
	STATUS_FILE = open("./db/status.log", "a")
	
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