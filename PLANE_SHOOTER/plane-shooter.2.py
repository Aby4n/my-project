import pygame
import random
import sys

pygame.init()

# Ukuran layar
WIDTH = 500
HEIGHT = 600
screen = pygame.display.set_mode((WIDTH, HEIGHT))
pygame.display.set_caption("Plane Shooter 2D")

# Warna
WHITE = (255, 255, 255)
RED = (255, 0, 0)
GREEN = (0, 255, 0)
BLACK = (0, 0, 0)

# Font
font_big = pygame.font.SysFont(None, 60)
font_small = pygame.font.SysFont(None, 30)

clock = pygame.time.Clock()

# MENU
def menu():
    while True:
        screen.fill(BLACK)

        title = font_big.render("PLANE SHOOTER", True, WHITE)
        start = font_small.render("Tekan ENTER untuk mulai", True, WHITE)

        screen.blit(title, (60, 200))
        screen.blit(start, (120, 300))

        pygame.display.update()

        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                pygame.quit()
                sys.exit()

            if event.type == pygame.KEYDOWN:
                if event.key == pygame.K_RETURN:
                    return

# GAME OVER 
def game_over(score):
    while True:
        screen.fill(BLACK)

        over_text = font_big.render("GAME OVER", True, RED)
        score_text = font_small.render(f"Score: {score}", True, WHITE)
        retry_text = font_small.render("Tekan R untuk ulang", True, WHITE)

        screen.blit(over_text, (120, 200))
        screen.blit(score_text, (180, 280))
        screen.blit(retry_text, (140, 330))

        pygame.display.update()

        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                pygame.quit()
                sys.exit()

            if event.type == pygame.KEYDOWN:
                if event.key == pygame.K_r:
                    return

# GAME
def game():
    # Player
    player_width = 50
    player_height = 50
    player_x = WIDTH // 2
    player_y = HEIGHT - 70
    player_speed = 5

    # HP
    hp = 3

    # Bullet
    bullets = []
    bullet_speed = 7

    # Enemy
    enemies = []
    enemy_speed = 3

    # Score
    score = 0

    running = True
    while running:
        clock.tick(60)
        screen.fill(BLACK)

        # Event
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                pygame.quit()
                sys.exit()

        # Kontrol
        keys = pygame.key.get_pressed()
        if keys[pygame.K_LEFT] and player_x > 0:
            player_x -= player_speed
        if keys[pygame.K_RIGHT] and player_x < WIDTH - player_width:
            player_x += player_speed
        if keys[pygame.K_SPACE]:
            bullets.append([player_x + player_width // 2, player_y])

        # Update bullets
        for bullet in bullets:
            bullet[1] -= bullet_speed
        bullets = [b for b in bullets if b[1] > 0]

        # Spawn enemy
        if random.randint(1, 30) == 1:
            enemies.append([random.randint(0, WIDTH - 40), 0])

        # Update enemy
        for enemy in enemies:
            enemy[1] += enemy_speed

        # Collision peluru
        for enemy in enemies[:]:
            for bullet in bullets[:]:
                if (enemy[0] < bullet[0] < enemy[0] + 40 and
                    enemy[1] < bullet[1] < enemy[1] + 40):
                    enemies.remove(enemy)
                    bullets.remove(bullet)
                    score += 1
                    break

        # Collision player (kena musuh)
        for enemy in enemies[:]:
            if (player_x < enemy[0] + 40 and
                player_x + player_width > enemy[0] and
                player_y < enemy[1] + 40 and
                player_y + player_height > enemy[1]):
                
                enemies.remove(enemy)
                hp -= 1

                if hp <= 0:
                    return score

        # Draw player
        pygame.draw.rect(screen, WHITE, (player_x, player_y, player_width, player_height))

        # Draw bullets
        for bullet in bullets:
            pygame.draw.rect(screen, RED, (bullet[0], bullet[1], 5, 10))

        # Draw enemies
        for enemy in enemies:
            pygame.draw.rect(screen, GREEN, (enemy[0], enemy[1], 40, 40))

        # UI
        score_text = font_small.render(f"Score: {score}", True, WHITE)
        hp_text = font_small.render(f"HP: {hp}", True, WHITE)

        screen.blit(score_text, (10, 10))
        screen.blit(hp_text, (10, 40))

        pygame.display.update()

# MAIN LOOP 
while True:
    menu()
    score = game()
    game_over(score)