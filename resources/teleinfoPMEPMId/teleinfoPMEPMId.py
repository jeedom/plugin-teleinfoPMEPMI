# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import logging
import sys
import os
import traceback
import signal
import argparse
import serial
from decode_pmepmi import LecturePortSerie
from decode_pmepmi import DecodeCompteurPmePmi
from decode_pmepmi import InterpretationTramesPmePmi

try:
    from jeedom.jeedom import *
except ImportError as e:
    print("Error: importing module jeedom.jeedom")
    sys.exit(1)

decode_pmepmi = DecodeCompteurPmePmi()
interpreteur_trames = InterpretationTramesPmePmi()


def cb_nouvelle_trame_interpretee_tt_interpretation(tableau_trame_interpretee):
    jeedom_com.send_change_immediate(tableau_trame_interpretee)


def cb_nouvel_octet_recu(octet_recu):
    decode_pmepmi.nouvel_octet(serial.to_bytes(octet_recu))


def cb_nouvelle_trame_recue():
    logging.debug("Last trame : %s",
                  decode_pmepmi.get_derniere_trame_valide())


interpreteur_trames.set_cb_nouvelle_interpretation_tt_interpretation(
    cb_nouvelle_trame_interpretee_tt_interpretation)
decode_pmepmi.set_cb_nouvelle_trame_recue_tt_trame(
    interpreteur_trames.interpreter_trame)
decode_pmepmi.set_cb_nouvelle_trame_recue(cb_nouvelle_trame_recue)

# ----------------------------------------------------------------------------


def handler(signum=None, frame=None):
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()


def shutdown():
    logging.debug("Shutdown")
    logging.debug("Removing PID file " + str(_pidfile))
    try:
        os.remove(_pidfile)
    except:
        pass
    try:
        lien_serie.close()
    except:
        pass
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)

# ----------------------------------------------------------------------------


_log_level = 'error'
_device = 'auto'
_serial_rate = 1200
_callback = ''
_apikey = ''
_pidfile = '/tmp/jeedom/teleinfoPMEPMI/teleinfoPMEPMId.pid'

parser = argparse.ArgumentParser(
    description='teleinfoPMEPMI Daemon for Jeedom')
parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--serialrate", help="Serial rate of device", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
args = parser.parse_args()

if args.device:
    _device = args.device
if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.serialrate:
    _serial_rate = int(args.serialrate)

jeedom_utils.set_log_level(_log_level)

logging.info('Start demond')
logging.info('Log level : '+str(_log_level))
logging.info('Device : '+str(_device))
logging.info('Serial rate : '+str(_serial_rate))
logging.info('PID file : '+str(_pidfile))
logging.info('Apikey : '+str(_apikey))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)

try:
    lien_serie = serial.Serial(port=_device,
                               baudrate=_serial_rate,
                               # baudrate = 1200,
                               # baudrate = 4800,
                               # baudrate = 9600,
                               # baudrate = 19200,
                               # baudrate = 38400,
                               # baudrate = 57600,
                               # baudrate = 115200,
                               bytesize=serial.SEVENBITS,
                               # bytesize=serial.EIGHTBITS,
                               parity=serial.PARITY_EVEN,
                               stopbits=serial.STOPBITS_ONE,
                               # stopbits=serial.STOPBITS_ONE_POINT_FIVE,
                               xonxoff=False,
                               rtscts=False,
                               dsrdtr=False,
                               timeout=1)
    logging.info('Serial port initialized')
except serial.SerialException as e:
    logging.error('Serial port init error : ' + str(e))
    shutdown()

try:
    jeedom_utils.write_pid(str(_pidfile))
    jeedom_com = jeedom_com(apikey=_apikey, url=_callback)
    if not jeedom_com.test():
        logging.error(
            'Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()
    lecture_serie = LecturePortSerie(lien_serie, cb_nouvel_octet_recu)
    lecture_serie.run()
except Exception as e:
    logging.error('Fatal error : '+str(e))
    logging.info(traceback.format_exc())
    shutdown()
