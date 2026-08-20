import os
import time
import pymysql
import serial

DB_HOST = os.getenv('DB_HOST', 'db')
DB_NAME = os.getenv('DB_NAME', 'oficina')
DB_USER = os.getenv('DB_USER', 'oficina')
DB_PASSWORD = os.getenv('DB_PASSWORD', 'oficina123')
SERIAL_PORT = os.getenv('SERIAL_PORT', '/dev/ttyUSB0')
BAUD_RATE = int(os.getenv('BAUD_RATE', 9600))

print(f"Arduino Bridge iniciando. DB: {DB_HOST}:{DB_NAME}, Serial: {SERIAL_PORT}", flush=True)

ser = None

def get_db_connection():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

def init_serial():
    global ser
    ports_to_try = [SERIAL_PORT, '/dev/ttyUSB0', '/dev/ttyACM0', '/dev/ttyUSB1', '/dev/ttyACM1']
    for port in ports_to_try:
        try:
            ser = serial.Serial(port, BAUD_RATE, timeout=2)
            print(f"Conectado a porta serial: {port}", flush=True)
            return True
        except Exception:
            continue
    print(f"Aviso: Nenhuma porta serial do Arduino encontrada ({ports_to_try}). Modo de simulacao ativo.", flush=True)
    return False

init_serial()

db_conn = None

while True:
    try:
        if db_conn is None or not db_conn.open:
            try:
                db_conn = get_db_connection()
                print("Conectado ao banco de dados MySQL com sucesso.", flush=True)
            except Exception as e:
                print(f"Erro ao conectar ao banco de dados: {e}. Tentando novamente...", flush=True)
                time.sleep(5)
                continue

        with db_conn.cursor() as cursor:
            cursor.execute(
                "SELECT id, placa, status, comando FROM arduino_comandos WHERE processado = 0 ORDER BY id ASC LIMIT 10"
            )
            comandos = cursor.fetchall()

            for cmd in comandos:
                cmd_id = cmd['id']
                placa = cmd['placa']
                status = cmd['status']
                comando = cmd['comando']

                mensagem = f"{placa}:{status}:{comando}\n"
                print(f"[COMANDO ARDUINO] ID={cmd_id} | Placa={placa} | Status={status} | Comando={comando}", flush=True)

                if ser and ser.is_open:
                    try:
                        ser.write(mensagem.encode('utf-8'))
                    except Exception as se:
                        print(f"Erro na transmissao serial: {se}", flush=True)
                        ser = None

                cursor.execute(
                    "UPDATE arduino_comandos SET processado = 1, processado_em = NOW() WHERE id = %s",
                    (cmd_id,)
                )
                print(f"[COMANDO PROCESSADO] ID={cmd_id}", flush=True)

    except Exception as e:
        print(f"Erro no loop principal: {e}", flush=True)
        db_conn = None

    time.sleep(2)