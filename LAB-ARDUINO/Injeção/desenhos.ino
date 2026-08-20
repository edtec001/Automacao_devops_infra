// -------------------------------------------------- Desenha o Velocímetro e tacômetro
void desenha_velo() {
  u8g2.drawCircle(cx, cy, radius_velo,   U8G2_DRAW_ALL);
}
void desenha_taco() {
  u8g2.drawCircle(cx_taco, cy_taco, radius_taco, U8G2_DRAW_ALL);
}

// ---------- Tics no Velocímetro  ----------
void  desenha_tics_velo()  {
  for (int v = 0; v <= VMAX; v += 20) {

    float ang = map(v, 0, VMAX, -30, 210);   // -150=0km, +30=200km
    float rad = radians(ang);
    int x1 = cx + cos(rad) * (radius_velo - 2);
    int y1 = cy - sin(rad) * (radius_velo - 2);
    int x2 = cx + cos(rad) * (radius_velo - 6);
    int y2 = cy - sin(rad) * (radius_velo - 6);
    u8g2.drawLine(x1, y1, x2, y2);
  }
}




// ---------- Tics no Tacômetro  ----------
void  desenha_tics_taco() {
  for (int v = 0; v <= RPM_MAX; v += 1000) {

    float ang = map(v, 0, RPM_MAX, -30, 210);   // -150=0km, +30=200km
    float rad = radians(ang);
    int x1 = cx_taco + cos(rad) * (radius_taco - 1);
    int y1 = cy_taco - sin(rad) * (radius_taco - 1);
    int x2 = cx_taco + cos(rad) * (radius_taco - 3);
    int y2 = cy_taco - sin(rad) * (radius_taco - 3);

    u8g2.drawLine(x1, y1, x2, y2);
  }
}



// -------------------------------------------------- Agulha do velocímetro
void move_agulha_velo(float speed) {

  // Bloqueia a agulha para não quebrar o instrumento rsrs
  if (speed < 0) speed = 0;
  if (speed > VMAX) speed = VMAX;


  float ang = ANG_ZERO + (speed / VMAX) * ANG_SWEEP;

  int x  = cx + cos(ang) * radius_velo;
  int y  = cy - sin(ang) * radius_velo;

  // deslocamento lateral (90°)
  float ang90 = ang + PI / 2;
  int dx = cos(ang90);
  int dy = sin(ang90);

  // linha central
  u8g2.drawLine(cx, cy, x, y);

  // linhas laterais (corpo)
  u8g2.drawLine(cx + dx, cy + dy, x + dx, y + dy);
  u8g2.drawLine(cx - dx, cy - dy, x - dx, y - dy);

  // Engrossa o centro
  u8g2.drawDisc(cx, cy, 3);
}



// -------------------------------------------------- Agulha do Tacômetro
void move_agulha_taco(float rot) {

  // Bloqueia a agulha para não quebrar o instrumento rsrs
  if (rot < 0) rot = 0;
  if (rot > RPM_MAX) rot = RPM_MAX;

  // Serial.print (" rot: "); Serial.println (rot);

  float ang = ANG_ZERO + (rot / RPM_MAX) * ANG_SWEEP;

  int x  = cx_taco + cos(ang) * radius_taco;
  int y  = cy_taco - sin(ang) * radius_taco;

  // deslocamento lateral (90°)
  float ang90 = ang + PI / 2;
  int dx = cos(ang90);
  int dy = sin(ang90);

  // linha central
  u8g2.drawLine(cx_taco, cy_taco, x, y);

  // linhas laterais (corpo)
  u8g2.drawLine(cx_taco + dx, cy_taco + dy, x + dx, y + dy);
  u8g2.drawLine(cx_taco - dx, cy_taco - dy, x - dx, y - dy);

  // Engrossa o centro
  u8g2.drawDisc(cx_taco, cy_taco, 3);
}



// -------------------------------------------------- Escreve os valores
void drawValues() {
  char buf[10];
  int w;


  // ---------- RPM ----------

  u8g2.setFont(u8g2_font_4x6_tf);                                   // Fonte Menor para escrever "RPM"
  u8g2.drawStr(79, 38, "RPM");

  u8g2.setFont(u8g2_font_6x10_tf);                                   // Muda para fonte grande para os valores gerais impressos no instrumento
  sprintf(buf, "%d", round(rpm));
  w = u8g2.getStrWidth(buf);
  u8g2.drawStr(cx_taco - w / 2, 51, buf);
  

  // ---------- VELOCIDADE ----------
  sprintf(buf, "%d", round(velocidade));
  w = u8g2.getStrWidth(buf);
  u8g2.drawStr(cx - w / 2, 48, buf);

  w = u8g2.getStrWidth("km/h");
  u8g2.drawStr(cx - w / 2, 55, "km/h");


  // ---------- COMB ----------
  sprintf(buf, "%d%%", round(pct_comb));
  u8g2.drawStr(95, 62, buf);

  // Desenha a escala do marcador de combustível
  u8g2.drawLine(118, 57, 120, 57);  //0%
  u8g2.drawLine(118, 52, 120, 52);  //10%
  u8g2.drawLine(118, 47, 120, 47);  //20%
  u8g2.drawLine(118, 41, 120, 41);  //30%
  u8g2.drawLine(118, 36, 120, 36);  //40%
  u8g2.drawLine(117, 31, 123, 31);  //50%
  u8g2.drawLine(118, 26, 120, 26);  //60%
  u8g2.drawLine(118, 21, 120, 21);  //70%
  u8g2.drawLine(118, 17, 120, 17);  //80%
  u8g2.drawLine(118, 12, 120, 12);  //90%
  u8g2.drawLine(118, 7, 120, 7);  //100%

  // Desenha e move a seta móvel
  int posic_marcador = map(pct_comb, 0, 100, 50, 0);
  u8g2.drawTriangle(129, posic_marcador  , 129, posic_marcador + 12, 123, posic_marcador + 6);


  // ---------- Temperatura ----------
  // Escreve valor de temperatura
  sprintf(buf, "%d\260C", temp_motor);
  u8g2.drawStr(62, 62, buf);

}
