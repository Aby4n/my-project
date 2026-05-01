import pygame
import random
import sys

pygame.init()

WIDTH, HEIGHT = 500, 600
screen = pygame.display.set_mode((WIDTH, HEIGHT))
pygame.display.set_caption("Plane Shooter Advanced")

clock = pygame.time.Clock()

# Load gambar
player_img = pygame.image.load("plane_shooter_3/assets/player.png")
enemy_imgs = [
    pygame.image.load("assets/enemy1.png"),
    pygame.image.load("assets/enemy2.png"),
    pygame.image.load("assets/enemy3.png")
]
power_img = pygame.image.load("assets/powerup.png")

# Resize
player_img = pygame.transform.scale(player_img, (50, 50))
enemy_imgs = [pygame.transform.scale(img, (40, 40)) for img in enemy_imgs]
power_img = pygame.transform.scale(power_img, (30, 30))

font = pygame.font.SysFont(None, 30)

# CLASS
class Player:
    def __init__(self):
        self.x = WIDTH // 2
        self.y = HEIGHT - 70
        self.speed = 5
        self.hp = 3
        self.cooldown = 0

    def draw(self):
        screen.blit(player_img, (self.x, self.y))

    def move(self, keys):
        if keys[pygame.K_LEFT] and self.x > 0:
            self.x -= self.speed
        if keys[pygame.K_RIGHT] and self.x < WIDTH - 50:
            self.x += self.speed

class Enemy:
    def __init__(self):
        self.type = random.randint(0, 2)
        self.x = random.randint(0, WIDTH - 40)
        self.y = 0

        if self.type == 0:  # normal
            self.speed = 3
            self.hp = 1
        elif self.type == 1:  # cepat
            self.speed = 6
            self.hp = 1
        else:  # tank
            self.speed = 2
            self.hp = 3

    def draw(self):
        screen.blit(enemy_imgs[self.type], (self.x, self.y))

class Bullet:
    def __init__(self, x, y):
        self.x = x
        self.y = y
        self.speed = 7

    def move(self):
        self.y -= self.speed

    def draw(self):
        pygame.draw.rect(screen, (255,0,0), (self.x, self.y, 5, 10))

class PowerUp:
    def __init__(self):
        self.x = random.randint(0, WIDTH - 30)
        self.y = 0
        self.type = random.choice(["heal", "rapid"])
        self.speed = 3

    def draw(self):
        screen.blit(power_img, (self.x, self.y))

# GAME
def game():
    player = Player()
    bullets = []
    enemies = []
    powers = []

    score = 0
    rapid_timer = 0

    while True:
        clock.tick(60)
        screen.fill((0,0,0))

        # Event
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                pygame.quit()
                sys.exit()

        keys = pygame.key.get_pressed()
        player.move(keys)

        # Shoot
        if player.cooldown == 0:
            if keys[pygame.K_SPACE]:
                bullets.append(Bullet(player.x + 25, player.y))
                player.cooldown = 10 if rapid_timer <= 0 else 3

        if player.cooldown > 0:
            player.cooldown -= 1

        # Spawn musuh
        if random.randint(1, 25) == 1:
            enemies.append(Enemy())

        # Spawn powerup
        if random.randint(1, 200) == 1:
            powers.append(PowerUp())

        # Update bullet
        for b in bullets[:]:
            b.move()
            if b.y < 0:
                bullets.remove(b)

        # Update enemy
        for e in enemies[:]:
            e.y += e.speed

            # Kena player
            if (player.x < e.x + 40 and player.x + 50 > e.x and
                player.y < e.y + 40 and player.y + 50 > e.y):
                enemies.remove(e)
                player.hp -= 1

        # Collision bullet-enemy
        for e in enemies[:]:
            for b in bullets[:]:
                if (e.x < b.x < e.x+40 and e.y < b.y < e.y+40):
                    e.hp -= 1
                    bullets.remove(b)
                    if e.hp <= 0:
                        enemies.remove(e)
                        score += 1
                    break

        # Update powerup
        for p in powers[:]:
            p.y += p.speed

            if (player.x < p.x + 30 and player.x + 50 > p.x and
                player.y < p.y + 30 and player.y + 50 > p.y):

                if p.type == "heal":
                    player.hp += 1
                else:
                    rapid_timer = 300  # rapid fire

                powers.remove(p)

        if rapid_timer > 0:
            rapid_timer -= 1

        # Draw
        player.draw()

        for b in bullets:
            b.draw()

        for e in enemies:
            e.draw()

        for p in powers:
            p.draw()

        # UI
        screen.blit(font.render(f"HP: {player.hp}", True, (255,255,255)), (10,10))
        screen.blit(font.render(f"Score: {score}", True, (255,255,255)), (10,40))

        pygame.display.update()

        if player.hp <= 0:
            return score

# MAIN 
while True:
    score = game()
    print("Game Over! Score:", score)