/*
 ==================================================================
 FUNÇÕES AUXILIARES E DE INTERRUPÇÃO DO INJETOR DE SINAIS
 ==================================================================
*/

#include <Arduino.h>
#include <LiquidCrystal.h>
#include <TimerOne.h>

extern LiquidCrystal lcd;
extern String nome_do_sinal;
extern int dentes_da_roda;
extern int dentes_totais;
extern int falhas;
extern int key;
extern float rot_por_minuto;
extern float tempo;
extern int start;
extern int submenu;
extern int roda;
extern int opc_menu;
extern int contador;
extern int cont_virab;

void reset_default();
void lcd_opcoes_basicas();

// --------------------------------------------------
// Leitura do teclado do LCD Keypad Shield (Pino A0)
// --------------------------------------------------
void teclado() {
  int adc = analogRead(A0);
  if (adc > 1000)      key = 0; // Nenhum botao
  else if (adc < 50)   key = 1; // RIGHT
  else if (adc < 195)  key = 2; // UP
  else if (adc < 380)  key = 3; // DOWN
  else if (adc < 555)  key = 4; // LEFT
  else if (adc < 790)  key = 5; // SELECT
  else                 key = 0;
}

// --------------------------------------------------
// Checa navegacao nas opcoes do menu de sinais
// --------------------------------------------------
void checa_opcao() {
  teclado();
  
  if (key == 2) { // UP
    delay(200);
    roda--;
    if (roda < 101) roda = 104;
    key = 0;
    lcd.clear();
  }
  
  if (key == 3) { // DOWN
    delay(200);
    roda++;
    if (roda > 104) roda = 101;
    key = 0;
    lcd.clear();
  }

  if (key == 5) { // SELECT - Inicia a geracao do sinal
    delay(200);
    start = 1;
    key = 0;
  }

  if (key == 4) { // LEFT - Volta ao menu principal
    delay(200);
    reset_default();
  }
}

// --------------------------------------------------
// Interrupcao de Timer1 - Geracao da Roda Fonica
// --------------------------------------------------
void roda_fonica() {
  if (start == 0) {
    digitalWrite(11, LOW);
    digitalWrite(12, LOW);
    digitalWrite(13, LOW);
    digitalWrite(3, LOW);
    contador = 0;
    return;
  }

  contador++;
  if (contador > dentes_da_roda) {
    contador = 1;
    cont_virab++;
    if (cont_virab > 2) {
      cont_virab = 1;
    }
  }

  // Calculo de dentes ativos vs dentes de falha (gap)
  // Cada dente tem 2 estados na interrupcao (subida/descida)
  int passos_ativos = (dentes_totais - falhas) * 2;

  if (contador <= passos_ativos) {
    // Dente ativo: alterna sinal
    if (contador % 2 != 0) {
      // Meio dente nivel alto
      digitalWrite(11, HIGH); // Sinal Hall (0-5V)
      digitalWrite(13, HIGH); // Indutivo CKP+
      digitalWrite(12, LOW);  // Indutivo CKP- (invertido)
    } else {
      // Meio dente nivel baixo
      digitalWrite(11, LOW);  // Sinal Hall
      digitalWrite(13, LOW);  // Indutivo CKP+
      digitalWrite(12, HIGH); // Indutivo CKP- (invertido)
    }
  } else {
    // Falha de dente (gap): mantem tudo zerado
    digitalWrite(11, LOW);
    digitalWrite(12, LOW);
    digitalWrite(13, LOW);
  }

  // Sinal de Fase do Comando de Valvulas (Pin 3)
  // Gera 1 pulso de 1 dente na primeira volta do virabrequim (1 volta de comando = 2 do virabrequim)
  if (cont_virab == 1 && contador <= 2) {
    digitalWrite(3, HIGH);
  } else {
    digitalWrite(3, LOW);
  }
}

// --------------------------------------------------
// Loop de Geracao e Ajuste de Rotação (RPM)
// --------------------------------------------------
void velocidade() {
  if (rot_por_minuto < 300) rot_por_minuto = 1000;
  
  // Atualiza tempo de interrupcao Timer1 (em microsegundos)
  // tempo = (30.000.000) / (RPM * dentes_totais)
  tempo = (30000000.0) / (rot_por_minuto * (float)dentes_totais);
  Timer1.setPeriod((long)tempo);
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(nome_do_sinal);
  lcd.print(" ");
  lcd.print((int)rot_por_minuto);
  lcd.print(" RPM");

  lcd.setCursor(0, 1);
  lcd.print("U/D:RPM  SEL:Sair");

  unsigned long ultimo_update = millis();

  while (start == 1) {
    teclado();

    bool mudou_rpm = false;

    if (key == 2) { // UP: +250 RPM
      rot_por_minuto += 250;
      if (rot_por_minuto > 7000) rot_por_minuto = 7000;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 3) { // DOWN: -250 RPM
      rot_por_minuto -= 250;
      if (rot_por_minuto < 300) rot_por_minuto = 300;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 1) { // RIGHT: +1000 RPM
      rot_por_minuto += 1000;
      if (rot_por_minuto > 7000) rot_por_minuto = 7000;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 4) { // LEFT: -1000 RPM
      rot_por_minuto -= 1000;
      if (rot_por_minuto < 300) rot_por_minuto = 300;
      mudou_rpm = true;
      delay(150);
    }
    else if (key == 5) { // SELECT: Sair / Parar Sinal
      start = 0;
      delay(250);
      lcd.clear();
      reset_default();
      return;
    }

    // Leitura opcional de potenciometro em A1 para controle analogico
    int pot = analogRead(A1);
    if (pot > 20 && pot < 1000) { // Se houver potenciometro conectado em A1
      static int pot_ant = 0;
      if (abs(pot - pot_ant) > 15) {
        pot_ant = pot;
        rot_por_minuto = map(pot, 0, 1023, 300, 7000);
        mudou_rpm = true;
      }
    }

    if (mudou_rpm) {
      tempo = (30000000.0) / (rot_por_minuto * (float)dentes_totais);
      Timer1.setPeriod((long)tempo);
      
      lcd.setCursor(0, 0);
      lcd.print("                ");
      lcd.setCursor(0, 0);
      lcd.print(nome_do_sinal);
      lcd.print(" ");
      lcd.print((int)rot_por_minuto);
      lcd.print(" RPM");
    }

    // Atualiza LCD periodicamente
    if (millis() - ultimo_update > 500) {
      ultimo_update = millis();
      lcd.setCursor(0, 0);
      lcd.print(nome_do_sinal);
      lcd.print(" ");
      lcd.print((int)rot_por_minuto);
      lcd.print(" RPM ");
    }
  }

  // Parar saídas quando sair
  digitalWrite(11, LOW);
  digitalWrite(12, LOW);
  digitalWrite(13, LOW);
  digitalWrite(3, LOW);
}
