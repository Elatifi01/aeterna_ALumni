import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
import numpy as np
from PIL import Image, ImageDraw, ImageFont

output_dir = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets"
os.makedirs(output_dir, exist_ok=True)

# -------------------------------------------------------------
# 1. LUXURY CORPORATE LOGO FOR AETERNA DIGITAL SOLUTIONS
# -------------------------------------------------------------
def generate_logo():
    img_size = (1200, 360)
    img = Image.new('RGBA', img_size, (255, 255, 255, 0))
    draw = ImageDraw.Draw(img)

    # Gradient or solid background badge on left for icon
    # Draw stylized geometric emblem: hexagon / diamond with 'A' & infinity
    icon_center = (180, 180)
    r = 130
    
    # Outer circle/hexagon in deep navy #16375A
    navy = (22, 55, 90, 255)
    gold = (217, 119, 6, 255)
    gold_light = (245, 158, 11, 255)
    slate = (100, 116, 139, 255)

    # Draw rounded shield / octagon
    draw.rounded_rectangle([40, 40, 320, 320], radius=40, fill=(248, 250, 252, 255), outline=navy, width=6)
    draw.rounded_rectangle([52, 52, 308, 308], radius=32, outline=gold, width=3)

    # Draw geometric stylized letter "A"
    # Apex at (180, 85), Left base (105, 265), Right base (255, 265)
    apex = (180, 95)
    l_base = (115, 260)
    r_base = (245, 260)
    draw.polygon([apex, l_base, (135, 260), (180, 150), (225, 260), r_base], fill=navy)
    # Crossbar in gold
    draw.rounded_rectangle([135, 195, 225, 215], radius=6, fill=gold)
    # Center diamond jewel
    draw.polygon([(180, 125), (192, 142), (180, 159), (168, 142)], fill=gold)

    # Company Text on right
    # Since standard truetype fonts might vary, we draw clean sharp typography
    try:
        font_main = ImageFont.truetype("arialbd.ttf", 68)
        font_sub = ImageFont.truetype("arial.ttf", 26)
        font_tagline = ImageFont.truetype("ariali.ttf", 20)
    except:
        font_main = ImageFont.load_default()
        font_sub = ImageFont.load_default()
        font_tagline = ImageFont.load_default()

    draw.text((360, 85), "AETERNA", fill=navy, font=font_main)
    draw.text((364, 170), "DIGITAL SOLUTIONS", fill=gold, font=font_sub)
    draw.text((364, 215), "Architecture Cloud & Solutions d'Éducation Supérieure", fill=slate, font=font_tagline)

    # Accent decorative line
    draw.line([(364, 255), (1050, 255)], fill=(226, 232, 240, 255), width=3)
    draw.line([(364, 255), (550, 255)], fill=gold, width=3)

    logo_path = os.path.join(output_dir, "aeterna_logo_badge.png")
    img.save(logo_path, "PNG")
    print(f"Logo generated: {logo_path}")

# -------------------------------------------------------------
# 2. MULTI-TENANT ARCHITECTURE SCHEMA (Matplotlib)
# -------------------------------------------------------------
def generate_architecture():
    fig, ax = plt.subplots(figsize=(12, 7), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    # Boxes:
    # 1. Clients / Users
    # 2. Security & Routing Layer (Cloudflare / Nginx / SSL)
    # 3. Core Laravel 11 Multi-Tenant Engine
    # 4. Inertia.js / React 18 SPA Frontend
    # 5. Database Layer (Tenant 1, Tenant 2, Central DB)
    # 6. External Integrations (CNDP Vault, Email, ENT)

    def draw_card(x, y, w, h, title, subtitle, bg_color='#FFFFFF', border_color='#16375A', title_color='#16375A'):
        rect = patches.FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.03,rounding_size=0.15",
                                      facecolor=bg_color, edgecolor=border_color, linewidth=2, zorder=2)
        ax.add_patch(rect)
        ax.text(x + w/2, y + h - 0.25, title, ha='center', va='top', fontsize=11, fontweight='bold', color=title_color, zorder=3)
        ax.text(x + w/2, y + 0.3, subtitle, ha='center', va='bottom', fontsize=8.5, color='#475569', zorder=3)

    # Draw layers
    # Layer 1: Utilisateurs
    draw_card(0.5, 5.0, 2.5, 1.4, "Acteurs & Accès", "Étudiants | Lauréats | Admins\nAuth Laravel Fortify / 2FA", bg_color='#EFF6FF', border_color='#3B82F6')
    
    # Layer 2: Frontend
    draw_card(3.8, 5.0, 2.8, 1.4, "Frontend Hybride", "React 18 + Inertia.js\nTailwind CSS (Zero Latency SPA)", bg_color='#FEF3C7', border_color='#D97706', title_color='#B45309')

    # Layer 3: Backend API / Multi-tenant Core
    draw_card(7.4, 4.2, 4.0, 2.2, "Moteur SaaS Laravel 11", "• Middleware Multi-Tenant Resolver\n• Eloquent ORM & Data Scopes\n• RBAC Policies & Gates\n• Queues & Jobs Asynchrones", bg_color='#F1F5F9', border_color='#16375A')

    # Layer 4: Storage & Data
    draw_card(1.0, 1.2, 4.5, 2.0, "Persistance & Données", "• Tenant Database Isolée (Univ. Hassan II)\n• Tenant Database Écoles Partenaires\n• Chiffrement AES-256 des Profils", bg_color='#F0FDF4', border_color='#16A34A', title_color='#15803D')

    # Layer 5: Sécurité & Tiers
    draw_card(6.5, 1.2, 4.9, 2.0, "Conformité CNDP & Services Cloud", "• Registre de Traitement CNDP (Loi 09-08)\n• Hébergement Cloud Souverain & Backups\n• Notifications Mail & Webhooks ENT", bg_color='#FAF5FF', border_color='#9333EA', title_color='#7E22CE')

    # Connectors / Arrows
    arrow_props = dict(facecolor='#16375A', edgecolor='none', width=2, headwidth=8, zorder=1)
    
    ax.annotate('', xy=(3.8, 5.7), xytext=(3.0, 5.7), arrowprops=arrow_props)
    ax.annotate('', xy=(7.4, 5.7), xytext=(6.6, 5.7), arrowprops=arrow_props)
    ax.annotate('', xy=(4.0, 3.2), xytext=(8.0, 4.2), arrowprops=dict(facecolor='#16A34A', edgecolor='none', width=2, headwidth=8))
    ax.annotate('', xy=(8.5, 3.2), xytext=(9.2, 4.2), arrowprops=dict(facecolor='#9333EA', edgecolor='none', width=2, headwidth=8))

    ax.set_xlim(0, 12)
    ax.set_ylim(0.5, 7.2)
    ax.axis('off')
    plt.title("Architecture Système & Étanchéité Multi-Tenant — Aeterna Platform", fontsize=14, fontweight='bold', color='#16375A', pad=15)

    arch_path = os.path.join(output_dir, "architecture_schema.png")
    plt.tight_layout()
    plt.savefig(arch_path, bbox_inches='tight', dpi=200)
    plt.close()
    print(f"Architecture diagram generated: {arch_path}")

# -------------------------------------------------------------
# 3. FINANCIAL PROJECTIONS 3-YEAR CHART (Matplotlib)
# -------------------------------------------------------------
def generate_financial_chart():
    fig, ax = plt.subplots(figsize=(10, 5.2), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    annees = ['Année 1 (Lancement)', 'Année 2 (Croissance)', 'Année 3 (Maturité/Maghreb)']
    ca = [117, 328, 760]  # En milliers de MAD
    charges = [85, 183, 380]
    resultat_net = [32, 145, 380]

    x = np.arange(len(annees))
    width = 0.26

    rects1 = ax.bar(x - width, ca, width, label='Chiffre d\'Affaires (kMAD)', color='#16375A', edgecolor='#0F243A', zorder=3)
    rects2 = ax.bar(x, charges, width, label='Charges d\'Exploitation (kMAD)', color='#94A3B8', edgecolor='#64748B', zorder=3)
    rects3 = ax.bar(x + width, resultat_net, width, label='Résultat Net Bénéficiaire (kMAD)', color='#D97706', edgecolor='#B45309', zorder=3)

    ax.set_ylabel('Montant (en milliers de Dirhams — kMAD)', fontsize=11, fontweight='bold', color='#1E293B')
    ax.set_title('Modèle Économique Prévisionnel sur 3 Ans — Rentabilité Aeterna SaaS', fontsize=13, fontweight='bold', color='#16375A', pad=15)
    ax.set_xticks(x)
    ax.set_xticklabels(annees, fontsize=10, fontweight='bold', color='#334155')
    ax.legend(frameon=True, facecolor='#FFFFFF', edgecolor='#CBD5E1', fontsize=9.5)
    ax.grid(axis='y', linestyle='--', alpha=0.5, zorder=0)

    # Values on top of bars
    def autolabel(rects, is_bold=False):
        for rect in rects:
            height = rect.get_height()
            ax.annotate(f'{height}k',
                        xy=(rect.get_x() + rect.get_width() / 2, height),
                        xytext=(0, 3),  # 3 points vertical offset
                        textcoords="offset points",
                        ha='center', va='bottom', fontsize=8.5,
                        fontweight='bold' if is_bold else 'normal',
                        color='#1E293B')

    autolabel(rects1)
    autolabel(rects2)
    autolabel(rects3, is_bold=True)

    fin_path = os.path.join(output_dir, "financial_chart.png")
    plt.tight_layout()
    plt.savefig(fin_path, bbox_inches='tight', dpi=200)
    plt.close()
    print(f"Financial chart generated: {fin_path}")

# -------------------------------------------------------------
# 4. GANTT SCHEDULE CHART (Matplotlib)
# -------------------------------------------------------------
def generate_gantt_chart():
    fig, ax = plt.subplots(figsize=(11, 5.0), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    tasks = [
        "1. Cadrage & Enquête Terrain",
        "2. Conception UX/UI & Charte",
        "3. Architecture & Modélisation BD",
        "4. Dév. Laravel Backend & Multi-Tenant",
        "5. Dév. React/Inertia Frontend",
        "6. Recette, Tests & Conformité CNDP",
        "7. Soutenance & Lancement Marché"
    ]
    starts = [1, 2, 2.5, 3, 3.5, 4.5, 5.5]
    durations = [1.2, 1.0, 0.8, 1.8, 1.8, 1.0, 0.5]
    colors = ['#3B82F6', '#8B5CF6', '#EC4899', '#16375A', '#0D9488', '#F59E0B', '#10B981']

    y_pos = np.arange(len(tasks))
    ax.barh(y_pos, durations, left=starts, align='center', color=colors, edgecolor='#1E293B', height=0.45, zorder=3)

    ax.set_yticks(y_pos)
    ax.set_yticklabels(tasks, fontsize=10, fontweight='bold', color='#1E293B')
    ax.invert_yaxis()
    ax.set_xlabel('Calendrier des Mois de Projet (M1 à M6)', fontsize=11, fontweight='bold', color='#1E293B')
    ax.set_title('Planning de Réalisation — Méthodologie Agile & Déploiement', fontsize=13, fontweight='bold', color='#16375A', pad=15)
    ax.set_xlim(0.5, 6.5)
    ax.set_xticks([1, 2, 3, 4, 5, 6])
    ax.set_xticklabels(['Mois 1', 'Mois 2', 'Mois 3', 'Mois 4', 'Mois 5', 'Mois 6'])
    ax.grid(axis='x', linestyle='--', alpha=0.5, zorder=0)

    gantt_path = os.path.join(output_dir, "gantt_chart.png")
    plt.tight_layout()
    plt.savefig(gantt_path, bbox_inches='tight', dpi=200)
    plt.close()
    print(f"Gantt chart generated: {gantt_path}")

if __name__ == "__main__":
    generate_logo()
    generate_architecture()
    generate_financial_chart()
    generate_gantt_chart()
    print("All graphic assets successfully created!")
