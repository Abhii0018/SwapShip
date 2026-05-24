#!/usr/bin/env python3
"""SwapShip — professional 14-slide academic presentation."""

from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE
from pptx.dml.color import RGBColor
from pptx.oxml.xmlchemy import OxmlElement

NAVY = RGBColor(30, 58, 95)
NAVY_DARK = RGBColor(20, 40, 68)
TEAL = RGBColor(13, 148, 136)
TEAL_LIGHT = RGBColor(204, 251, 241)
GOLD = RGBColor(180, 142, 45)
BG_PAGE = RGBColor(245, 247, 250)
BG_PANEL = RGBColor(255, 255, 255)
TEXT = RGBColor(26, 35, 48)
MUTED = RGBColor(90, 104, 120)
LINE = RGBColor(210, 218, 228)
WHITE = RGBColor(255, 255, 255)

FONT_TITLE = "Segoe UI"
FONT_BODY = "Segoe UI"

SLIDE_W = Inches(13.333)
SLIDE_H = Inches(7.5)
OUT = "/Users/abhishekkumar/Desktop/SwapShip/SwapShip_Presentation.pptx"
GITHUB = "https://github.com/Abhii0018/SwapShip.git"
DEPLOYED = "https://swapship.onrender.com"
DEMO = "https://www.youtube.com/watch?v=dQw4w9WgXcQ"


def fill_shape(shape, color, transparency=None):
    shape.fill.solid()
    shape.fill.fore_color.rgb = color
    if transparency is not None:
        shape.fill.transparency = transparency
    shape.line.fill.background()


def border(shape, color=LINE, pt=0.75):
    shape.line.color.rgb = color
    shape.line.width = Pt(pt)


def font(p, *, name=FONT_BODY, size=14, bold=False, color=TEXT, align=None):
    p.font.name = name
    p.font.size = Pt(size)
    p.font.bold = bold
    p.font.color.rgb = color
    if align:
        p.alignment = align


def transition(slide, kind="fade"):
    el = slide._element
    for c in list(el):
        if c.tag.endswith("transition"):
            el.remove(c)
    t = OxmlElement("p:transition")
    t.set("spd", "med")
    node = OxmlElement(f"p:{kind}" if kind in ("push", "wipe") else "p:fade")
    if kind in ("push", "wipe"):
        node.set("dir", "l")
    t.append(node)
    el.append(t)


def notes(slide, text):
    try:
        slide.notes_slide.notes_text_frame.text = text
    except Exception:
        pass


def bg_professional(slide, style="content"):
    base = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, SLIDE_H)
    fill_shape(base, BG_PAGE)
    arc = slide.shapes.add_shape(MSO_SHAPE.OVAL, Inches(10.8), Inches(-1.5), Inches(4), Inches(4))
    fill_shape(arc, TEAL, 0.88)
    header = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, Inches(1.15))
    fill_shape(header, NAVY if style != "title" else NAVY_DARK)
    strip = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(1.15), SLIDE_W, Inches(0.06))
    fill_shape(strip, GOLD)
    if style == "content":
        panel = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.55), Inches(1.45), Inches(12.25), Inches(5.35))
        fill_shape(panel, BG_PANEL)
        border(panel, LINE, 1)
        panel.adjustments[0] = 0.02


def footer(slide, n):
    bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.55), Inches(7.02), Inches(12.25), Pt(0.8))
    fill_shape(bar, LINE)
    l = slide.shapes.add_textbox(Inches(0.6), Inches(7.1), Inches(8), Inches(0.28))
    font(l.text_frame.paragraphs[0], size=9, color=MUTED)
    l.text_frame.paragraphs[0].text = "SwapShip  |  MVC Programing (INT221)  |  Abhishek Kumar  |  Reg. 12300520"
    r = slide.shapes.add_textbox(Inches(12.2), Inches(7.1), Inches(0.6), Inches(0.28))
    font(r.text_frame.paragraphs[0], size=9, color=MUTED, align=PP_ALIGN.RIGHT)
    r.text_frame.paragraphs[0].text = f"{n} / 14"


def slide_title(slide, title, subtitle=None):
    tb = slide.shapes.add_textbox(Inches(0.7), Inches(0.22), Inches(12), Inches(0.85))
    tf = tb.text_frame
    font(tf.paragraphs[0], name=FONT_TITLE, size=26, bold=True, color=WHITE)
    tf.paragraphs[0].text = title
    if subtitle:
        p2 = tf.add_paragraph()
        font(p2, size=12, color=TEAL_LIGHT)
        p2.text = subtitle


def add_bullets(slide, items, x=Inches(0.85), y=Inches(2.0), w=Inches(11.8), size=13, spacing=10):
    box = slide.shapes.add_textbox(x, y, w, Inches(4.8))
    tf = box.text_frame
    tf.word_wrap = True
    for i, item in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.text = item
        p.level = 0
        font(p, size=size, color=TEXT)
        p.space_after = Pt(spacing)


def add_two_col(slide, left_title, left_items, right_title, right_items, y=Inches(1.95)):
    lw, rw = Inches(5.75), Inches(5.75)
    lx, rx = Inches(0.85), Inches(6.85)
    for title, items, x, w in [(left_title, left_items, lx, lw), (right_title, right_items, rx, rw)]:
        hd = slide.shapes.add_textbox(x, y, w, Inches(0.4))
        font(hd.text_frame.paragraphs[0], size=14, bold=True, color=NAVY)
        hd.text_frame.paragraphs[0].text = title
        line = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, x, y + Inches(0.42), w, Pt(2))
        fill_shape(line, TEAL)
        add_bullets(slide, items, x=x, y=y + Inches(0.55), w=w, size=12, spacing=8)


def add_table_rows(slide, headers, rows, y=Inches(2.05)):
    cols = len(headers)
    tw = Inches(11.6)
    cw = tw / cols
    x0 = Inches(0.85)
    for c, h in enumerate(headers):
        x = x0 + c * cw
        cell = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, x, y, cw - Inches(0.05), Inches(0.45))
        fill_shape(cell, NAVY)
        tb = slide.shapes.add_textbox(x + Inches(0.08), y + Inches(0.06), cw - Inches(0.15), Inches(0.35))
        font(tb.text_frame.paragraphs[0], size=11, bold=True, color=WHITE)
        tb.text_frame.paragraphs[0].text = h
    for r, row in enumerate(rows):
        ry = y + Inches(0.5) + r * Inches(0.42)
        tint = BG_PAGE if r % 2 == 0 else WHITE
        for c, val in enumerate(row):
            x = x0 + c * cw
            cell = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, x, ry, cw - Inches(0.05), Inches(0.4))
            fill_shape(cell, tint)
            border(cell, LINE, 0.5)
            tb = slide.shapes.add_textbox(x + Inches(0.08), ry + Inches(0.05), cw - Inches(0.15), Inches(0.32))
            font(tb.text_frame.paragraphs[0], size=10, color=TEXT)
            tb.text_frame.paragraphs[0].text = str(val)


def flow_diagram(slide, steps, y=Inches(2.15)):
    n = len(steps)
    w = Inches(11.5) / n
    x0 = Inches(0.9)
    for i, (num, title, desc) in enumerate(steps):
        x = x0 + i * w
        box = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, x + Inches(0.08), y, w - Inches(0.16), Inches(1.55))
        fill_shape(box, TEAL_LIGHT if i % 2 == 0 else WHITE)
        border(box, TEAL, 1)
        box.adjustments[0] = 0.12
        num_tb = slide.shapes.add_textbox(x + Inches(0.2), y + Inches(0.12), w - Inches(0.35), Inches(0.35))
        font(num_tb.text_frame.paragraphs[0], size=16, bold=True, color=NAVY)
        num_tb.text_frame.paragraphs[0].text = num
        tit_tb = slide.shapes.add_textbox(x + Inches(0.2), y + Inches(0.48), w - Inches(0.35), Inches(0.45))
        font(tit_tb.text_frame.paragraphs[0], size=11, bold=True, color=TEXT)
        tit_tb.text_frame.paragraphs[0].text = title
        desc_tb = slide.shapes.add_textbox(x + Inches(0.2), y + Inches(0.95), w - Inches(0.35), Inches(0.5))
        tf = desc_tb.text_frame
        tf.word_wrap = True
        font(tf.paragraphs[0], size=9, color=MUTED)
        tf.paragraphs[0].text = desc
        if i < n - 1:
            arr = slide.shapes.add_shape(MSO_SHAPE.RIGHT_ARROW, x + w - Inches(0.12), y + Inches(0.62), Inches(0.28), Inches(0.2))
            fill_shape(arr, GOLD)


def link_button(slide, label, url, x, y, w=Inches(5.8)):
    btn = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, x, y, w, Inches(0.58))
    fill_shape(btn, TEAL)
    btn.adjustments[0] = 0.2
    tf = btn.text_frame
    tf.text = label
    font(tf.paragraphs[0], size=12, bold=True, color=WHITE, align=PP_ALIGN.CENTER)
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    btn.click_action.hyperlink.address = url


def build():
    prs = Presentation()
    prs.slide_width = SLIDE_W
    prs.slide_height = SLIDE_H
    blank = prs.slide_layouts[6]

    # 1 TITLE
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    fill_shape(s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, SLIDE_H), NAVY_DARK)
    fill_shape(s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(5.8), SLIDE_W, Inches(1.7)), NAVY)
    fill_shape(s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(1.12), SLIDE_W, Inches(0.07)), GOLD)
    arc = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(9), Inches(0.5), Inches(5), Inches(5))
    fill_shape(arc, TEAL, 0.75)
    card = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.9), Inches(1.6), Inches(11.5), Inches(4.0))
    fill_shape(card, WHITE)
    card.adjustments[0] = 0.03
    tb = s.shapes.add_textbox(Inches(1.3), Inches(2.0), Inches(10.8), Inches(3.2))
    tf = tb.text_frame
    font(tf.paragraphs[0], name=FONT_TITLE, size=54, bold=True, color=NAVY)
    tf.paragraphs[0].text = "SwapShip"
    p2 = tf.add_paragraph()
    font(p2, size=22, color=TEAL)
    p2.text = "Peer-to-Peer Marketplace for Buy, Sell & Exchange"
    p2.space_before = Pt(10)
    p3 = tf.add_paragraph()
    font(p3, size=15, color=TEXT)
    p3.text = "With Integrated Split Payments, Shipping & OTP Delivery"
    p3.space_before = Pt(14)
    p4 = tf.add_paragraph()
    font(p4, size=13, color=MUTED)
    p4.text = "Course: MVC Programing (INT221)  ·  Continuous Assessment 3"
    p4.space_before = Pt(22)
    p5 = tf.add_paragraph()
    font(p5, size=13, bold=True, color=NAVY)
    p5.text = "Abhishek Kumar  ·  Registration No. 12300520"
    p5.space_before = Pt(6)
    notes(s, "Introduce project name, your details, and one-line value proposition.")
    footer(s, 1)

    # 2 AGENDA
    s = prs.slides.add_slide(blank)
    transition(s, "push")
    bg_professional(s)
    slide_title(s, "Agenda", "Structure of this presentation")
    add_table_rows(
        s,
        ["#", "Topic", "Key Points Covered"],
        [
            ("01", "Introduction", "Project overview, objectives & scope"),
            ("02", "Problem Statement", "Challenges in P2P trading today"),
            ("03", "Proposed Solution", "SwapShip platform & value proposition"),
            ("04", "Core Features", "Marketplace, exchange, chat, payments, shipping"),
            ("05", "Technology Stack", "Laravel, database, integrations"),
            ("06", "Architecture & Flow", "MVC design & transaction lifecycle"),
            ("07", "Security & Users", "Roles, OTP, admin & trust layer"),
            ("08", "Demo & Conclusion", "Live links, outcomes & Q&A"),
        ],
    )
    notes(s, "Briefly walk through the agenda — sets expectations for evaluators.")
    footer(s, 2)

    # 3 INTRODUCTION
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Introduction", "Project overview & objectives")
    add_two_col(
        s,
        "What is SwapShip?",
        [
            "Web-based P2P marketplace built with Laravel 13",
            "Users buy, sell, or exchange items nationwide",
            "Combines listings, chat, Razorpay payments & shipment tracking",
            "OTP verification confirms successful delivery",
            "Reduces need for separate apps for each step of a deal",
        ],
        "Project Objectives",
        [
            "Provide a unified platform for peer-to-peer commerce",
            "Enable secure split-payment (escrow-style) transactions",
            "Automate shipment creation and live status updates",
            "Support real-time negotiation through in-app chat",
            "Ensure verified users and admin oversight for safety",
        ],
    )
    notes(s, "Explain SwapShip as your academic MVC project solving real marketplace problems.")
    footer(s, 3)

    # 4 PROBLEM
    s = prs.slides.add_slide(blank)
    transition(s, "wipe")
    bg_professional(s)
    slide_title(s, "Problem Statement", "Why existing P2P methods are insufficient")
    add_bullets(
        s,
        [
            "Trust Deficit — Buyers fear paying full amount upfront; sellers fear shipping without guaranteed payment.",
            "Fragmented Workflow — Listing on one app, chatting on another, payment via UPI, courier booked separately.",
            "No Delivery Proof — Offline deals lack tracking, OTP handover, or dispute-ready transaction records.",
            "Limited Verification — Open platforms allow fake listings and unverified counterparties.",
            "Time & Cost Overhead — Multiple middlemen and manual coordination increase friction and fees.",
        ],
        y=Inches(1.85),
        size=13,
    )
    call = s.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.85), Inches(5.55), Inches(11.6), Inches(0.75))
    fill_shape(call, TEAL_LIGHT)
    border(call, TEAL)
    ct = s.shapes.add_textbox(Inches(1.05), Inches(5.72), Inches(11.2), Inches(0.45))
    font(ct.text_frame.paragraphs[0], size=13, bold=True, color=NAVY)
    ct.text_frame.paragraphs[0].text = "Need: One trusted platform covering discovery → negotiation → payment → shipping → confirmation"
    notes(s, "Emphasize each pain point with a real-world example before presenting SwapShip.")
    footer(s, 4)

    # 5 SOLUTION
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Proposed Solution — SwapShip", "End-to-end peer-to-peer transaction platform")
    add_bullets(
        s,
        [
            "Single Laravel web application for the complete deal lifecycle",
            "Marketplace with search, filters, geo-location & Cloudinary image uploads",
            "Exchange requests with accept/reject, confirmation & deal-terms workflow",
            "Razorpay split payments — partial upfront, remaining at doorstep delivery",
            "Integrated shipments with AWB tracking, webhooks & event timeline",
            "Email OTP registration, Google OAuth, delivery OTP & admin dashboard",
        ],
        y=Inches(1.85),
        size=13,
    )
    notes(s, "Position SwapShip as the direct answer to every problem listed on the previous slide.")
    footer(s, 5)

    # 6 FEATURES
    s = prs.slides.add_slide(blank)
    transition(s, "push")
    bg_professional(s)
    slide_title(s, "Core Features Overview", "Six integrated modules")
    add_table_rows(
        s,
        ["Module", "Description", "Key Capability"],
        [
            ("Marketplace", "Item listings & discovery", "Explore, filter, saved searches, my-items dashboard"),
            ("Exchange", "Deal initiation & approval", "Request, accept, confirm, shipment gates"),
            ("Chat", "Buyer-seller coordination", "Messages, attachments, typing, read status"),
            ("Payments", "Razorpay integration", "Split pay, checkout, webhooks, order stages"),
            ("Shipping", "Logistics & tracking", "Pickup, events, provider webhooks, status sync"),
            ("Security", "Trust & administration", "OTP verify, Google login, admin panel"),
        ],
        y=Inches(1.82),
    )
    notes(s, "Overview slide — detail each module on following slides.")
    footer(s, 6)

    # 7 MARKETPLACE
    s = prs.slides.add_slide(blank)
    transition(s, "wipe")
    bg_professional(s)
    slide_title(s, "Marketplace Module", "Item listing, search & discovery")
    add_two_col(
        s,
        "User Features",
        [
            "Landing page with featured listings & marketplace intro",
            "Explore page: search by title, category, location, condition, price",
            "Create / edit / delete items with multiple images",
            "My Items & My Dashboard for seller management",
            "Saved searches for repeated filter combinations",
        ],
        "Technical Implementation",
        [
            "ItemController — landing, index, CRUD, suggestions",
            "Cloudinary PHP SDK for image storage & delivery",
            "OpenStreetMap Nominatim for location autocomplete",
            "Geo fields on items for location-aware browsing",
            "Rate-limited API endpoints for suggestions",
        ],
    )
    notes(s, "Demo: show Explore page and Add Item if live app is available.")
    footer(s, 7)

    # 8 EXCHANGE & CHAT
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Exchange & Chat Module", "Deal workflow & real-time communication")
    flow_diagram(
        s,
        [
            ("1", "Browse", "Find item"),
            ("2", "Request", "Send exchange"),
            ("3", "Chat", "Negotiate price"),
            ("4", "Accept", "Both confirm"),
            ("5", "Terms", "Deal terms set"),
            ("6", "Ship", "Create shipment"),
        ],
    )
    add_bullets(
        s,
        [
            "MessageController: attachments (image, PDF, audio), typing indicators, presence, block/report",
            "Pusher broadcasting for real-time updates; unread notification summary endpoint",
        ],
        y=Inches(4.0),
        size=12,
    )
    notes(s, "Walk through the 6-step flow diagram left to right.")
    footer(s, 8)

    # 9 PAYMENTS & SHIPPING
    s = prs.slides.add_slide(blank)
    transition(s, "push")
    bg_professional(s)
    slide_title(s, "Payments & Shipping", "Razorpay split pay + logistics + OTP delivery")
    add_two_col(
        s,
        "Payment System (Razorpay)",
        [
            "Escrow-style split: upfront amount + balance at delivery",
            "Checkout page, init-razorpay, pay & callback routes",
            "Webhook signature verification before updating orders",
            "Order tracks payment_status across all stages",
            "Platform fee percentage configurable via environment",
        ],
        "Shipping & OTP",
        [
            "Shipment auto-created when exchange is accepted",
            "Pickup scheduling, status patches & event log (AWB)",
            "ShippingProviderInterface + webhook controller",
            "Delivery OTP generate/verify before deal completion",
            "Admin dashboard tracks OTP generated vs verified",
        ],
    )
    notes(s, "Highlight trust: buyer never pays 100% upfront; seller gets proof of delivery.")
    footer(s, 9)

    # 10 TECH STACK
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Technology Stack", "Tools & frameworks used")
    add_table_rows(
        s,
        ["Layer", "Technology", "Purpose"],
        [
            ("Backend", "PHP 8.3, Laravel 13", "MVC app, routing, Eloquent ORM, auth"),
            ("Frontend", "Blade, Tailwind CSS, Alpine.js, Vite", "Responsive UI & asset build"),
            ("Database", "PostgreSQL / MySQL", "Users, items, exchanges, messages, orders"),
            ("Payments", "Razorpay API", "Online checkout & webhook processing"),
            ("Real-time", "Pusher PHP Server", "Chat updates & notifications"),
            ("Media", "Cloudinary", "Item image upload & CDN delivery"),
            ("Auth", "Laravel Breeze, Socialite", "Login, register, Google OAuth, email OTP"),
            ("Deploy", "Render, Docker, Procfile", "Cloud hosting & health check endpoint"),
        ],
        y=Inches(1.78),
    )
    notes(s, "Show you understand full stack — backend, frontend, DB, third-party services.")
    footer(s, 10)

    # 11 ARCHITECTURE
    s = prs.slides.add_slide(blank)
    transition(s, "wipe")
    bg_professional(s)
    slide_title(s, "System Architecture", "MVC pattern & data flow")
    add_two_col(
        s,
        "MVC Layers",
        [
            "Model — User, Item, ExchangeRequest, Message, Shipment, Order, DeliveryOtp",
            "View — Blade templates (welcome, explore, chat, shipments, checkout)",
            "Controller — Item, ExchangeRequest, Message, Shipment, Payment, Admin",
            "Services — ShippingProviderInterface, DealTermsService",
            "Policies & Middleware — ShipmentPolicy, AdminMiddleware, EnsureUserIsVerified",
        ],
        "Transaction Lifecycle",
        [
            "User registers → Email OTP / Google OAuth verification",
            "Lists or browses items → Sends exchange request",
            "Chat & deal terms → Accept & confirm exchange",
            "Upfront Razorpay payment → Shipment created & tracked",
            "Delivery OTP verified → Exchange marked complete",
        ],
    )
    notes(s, "Classic MVC slide for academic evaluators — connect to your course.")
    footer(s, 11)

    # 12 SECURITY
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Security & User Roles", "Access control & trust mechanisms")
    add_table_rows(
        s,
        ["User Role", "Permissions", "Restrictions"],
        [
            ("Guest", "View landing & explore listings", "Cannot post or transact"),
            ("Registered", "Post items, chat, send requests", "Must verify for sensitive actions"),
            ("Verified", "Confirm exchanges, pay, ship", "Profile phone/address required"),
            ("Admin", "Admin dashboard, order/OTP tracking", "Protected by admin middleware"),
        ],
        y=Inches(1.78),
    )
    add_bullets(
        s,
        ["Security: CSRF protection, rate limiting, Razorpay webhook signatures, session timeout, banned users"],
        y=Inches(4.85),
        size=12,
    )
    notes(s, "Four roles table + security bullets — shows thoughtful access design.")
    footer(s, 12)

    # 13 DEMO
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    bg_professional(s)
    slide_title(s, "Live Demo & Project Links", "Click during slideshow to open")
    link_button(s, "Live Application — swapship.onrender.com", DEPLOYED, Inches(0.95), Inches(2.0))
    link_button(s, "GitHub Source Code — Abhii0018/SwapShip", GITHUB, Inches(0.95), Inches(2.85))
    link_button(s, "Demo Video Walkthrough", DEMO, Inches(0.95), Inches(3.7))
    add_bullets(
        s,
        [
            "Suggested demo flow: Register → Add item → Send request → Chat → Payment → Track shipment",
            "Health endpoint: /healthz for deployment monitoring",
            "Integrations: FedEx, DHL, Delhivery-style shipping (mock provider for dev)",
        ],
        x=Inches(7.0),
        y=Inches(2.0),
        w=Inches(5.5),
        size=12,
    )
    notes(s, "Open live app in browser. Walk through one complete happy-path transaction.")
    footer(s, 13)

    # 14 CONCLUSION
    s = prs.slides.add_slide(blank)
    transition(s, "fade")
    fill_shape(s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, SLIDE_H), NAVY_DARK)
    fill_shape(s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(1.1), SLIDE_W, Inches(0.06)), GOLD)
    tb = s.shapes.add_textbox(Inches(1.0), Inches(2.0), Inches(11.3), Inches(3.5))
    tf = tb.text_frame
    font(tf.paragraphs[0], name=FONT_TITLE, size=40, bold=True, color=WHITE)
    tf.paragraphs[0].text = "Conclusion"
    tf.paragraphs[0].alignment = PP_ALIGN.CENTER
    p2 = tf.add_paragraph()
    font(p2, size=14, color=TEAL_LIGHT)
    p2.text = (
        "SwapShip successfully integrates marketplace, chat, split payments, shipping & OTP "
        "into one Laravel MVC application — reducing P2P trade friction and building user trust."
    )
    p2.alignment = PP_ALIGN.CENTER
    p2.space_before = Pt(16)
    p3 = tf.add_paragraph()
    font(p3, size=13, color=WHITE)
    p3.text = "Future scope: mobile app, dispute resolution, ratings & reviews, multi-language support"
    p3.alignment = PP_ALIGN.CENTER
    p3.space_before = Pt(20)
    p4 = tf.add_paragraph()
    font(p4, size=22, bold=True, color=GOLD)
    p4.text = "Thank You — Questions?"
    p4.alignment = PP_ALIGN.CENTER
    p4.space_before = Pt(28)
    p5 = tf.add_paragraph()
    font(p5, size=12, color=MUTED)
    p5.text = "Abhishek Kumar  ·  INT221  ·  SwapShip"
    p5.alignment = PP_ALIGN.CENTER
    p5.space_before = Pt(12)
    notes(s, "Summarize achievements, mention future work, invite questions confidently.")
    footer(s, 14)

    prs.save(OUT)
    print(f"Saved: {OUT} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    build()
